"""Exercise real Nginx/FPM shutdown and shared immutable assets on a disposable runtime."""
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import uuid

image = sys.argv[1]
name = 'dlp-rolling-test-' + uuid.uuid4().hex[:10]
root = Path(__file__).resolve().parents[3]


def run(*args, **kwargs):
    return subprocess.check_output(list(args), stderr=subprocess.PIPE, **kwargs).decode()


with tempfile.TemporaryDirectory() as directory:
    probe = Path(directory) / 'probe.php'
    probe.write_text('''<?php
file_put_contents('/tmp/accepted', 'yes');
register_shutdown_function(function () { sleep(5); file_put_contents('/proof/completed', 'yes'); });
sleep(3);
echo 'write-completed';
fastcgi_finish_request();
''')
    Path(directory).chmod(0o777)
    request = None
    try:
        run('docker', 'run', '--detach', '--name', name,
            '--mount', 'type=bind,src=' + directory + ',dst=/proof',
            '--env', 'APP_ENV=production', '--env', 'APP_DEBUG=false', image)
        run('docker', 'cp', str(probe), name + ':/var/www/html/public/drain-probe.php')
        for attempt in range(100):
            try:
                run('docker', 'exec', name, 'curl', '--fail', '--silent', 'http://127.0.0.1/up', timeout=10)
                break
            except subprocess.CalledProcessError:
                time.sleep(.1)
        else:
            raise AssertionError('web did not start')
        run('docker', 'exec', name, 'rm', '-f', '/tmp/accepted', '/proof/completed')
        request = subprocess.Popen(['docker', 'exec', name, 'curl', '--fail', '--silent',
                                    'http://127.0.0.1/drain-probe.php'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        for attempt in range(100):
            if subprocess.run(['docker', 'exec', name, 'test', '-f', '/tmp/accepted'], capture_output=True).returncode == 0:
                break
            time.sleep(.1)
        else:
            raise AssertionError('request was not accepted')
        stopping = subprocess.Popen(['docker', 'stop', '--time', '30', name], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        try:
            for attempt in range(50):
                if subprocess.run(['docker', 'exec', name, 'test', '-f', '/var/www/html/storage/framework/web-draining'], capture_output=True).returncode == 0:
                    break
                time.sleep(.1)
            else:
                raise AssertionError('shutdown did not withdraw readiness before closing HTTP')
            assert run('docker', 'exec', name, 'curl', '--silent', '--output', '/dev/null', '--write-out', '%{http_code}',
                       'http://127.0.0.1/ready').strip() == '503', 'draining instance remained ready'
            assert run('docker', 'exec', name, 'curl', '--silent', '--output', '/dev/null', '--write-out', '%{http_code}',
                       'http://127.0.0.1/up').strip() == '200', 'HTTP stopped before proxy withdrawal'
            stopping.communicate(timeout=35)
            assert stopping.returncode == 0, 'container stop failed'
        finally:
            if stopping.poll() is None:
                stopping.kill()
                stopping.communicate()
        output, error = request.communicate(timeout=5)
        assert request.returncode == 0 and output == b'write-completed', (output, error)
        assert Path(directory, 'completed').read_text() == 'yes', 'PHP shutdown callback was killed'
        assert run('docker', 'inspect', name, '--format', '{{.State.ExitCode}}').strip() == '0', 'forced shutdown'
        print('Accepted HTTP write and post-response callback completed before container exit.')
    finally:
        if request and request.poll() is None:
            request.kill()
            request.communicate()
        subprocess.run(['docker', 'rm', '--force', name], capture_output=True)
