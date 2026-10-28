"""Retained and current chunks must both be served through a shared assets directory."""
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import uuid

image = sys.argv[1]
names = []


def run(*args):
    return subprocess.check_output(list(args), stderr=subprocess.PIPE).decode()


with tempfile.TemporaryDirectory() as directory:
    Path(directory).chmod(0o755)
    Path(directory, 'retained-old.js').write_text('retained-old-chunk')
    current = run('docker', 'run', '--rm', image, 'php', '-r',
                  '$m=json_decode(file_get_contents("public/build/manifest.json"),true); echo $m["resources/js/app.ts"]["file"];').strip()
    expected = run('docker', 'run', '--rm', image, 'cat', 'public/build/' + current)
    Path(directory, Path(current).name).write_text('truncated')
    try:
        for _ in range(2):
            name = 'dlp-assets-test-' + uuid.uuid4().hex[:10]
            run('docker', 'run', '--detach', '--name', name,
                '--mount', 'type=bind,src=' + directory + ',dst=/var/www/html/public/build/assets',
                '--env', 'APP_ENV=production', '--env', 'APP_DEBUG=false', image)
            names.append(name)
            for attempt in range(100):
                try:
                    run('docker', 'exec', name, 'curl', '--fail', '--silent', 'http://127.0.0.1/up')
                    break
                except subprocess.CalledProcessError:
                    time.sleep(.1)
            else:
                raise AssertionError('web did not start')
        for name in names:
            assert run('docker', 'exec', name, 'curl', '--fail', '--silent',
                       'http://127.0.0.1/build/assets/retained-old.js') == 'retained-old-chunk'
            assert run('docker', 'exec', name, 'curl', '--fail', '--silent',
                       'http://127.0.0.1/build/' + current) == expected, 'current chunk corrupted'
        print('Both web containers serve retained and current chunks; manifests stay private to each image.')
    finally:
        for name in names:
            subprocess.run(['docker', 'rm', '--force', name], capture_output=True)
