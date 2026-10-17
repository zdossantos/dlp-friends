"""Real Laravel/PhpRedis/FPM boundaries; synthetic resources on a private Docker network."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import uuid

root = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(root / "scripts/operations"))
spec = importlib.util.spec_from_file_location("release", root / "scripts/operations/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
image = sys.argv[1]
identifier = "dlp-release-test-" + uuid.uuid4().hex[:12]
network, redis = identifier, identifier + "-redis"
containers = []


def command(*args, input=None):
    return subprocess.check_output(list(args), input=input, stderr=subprocess.PIPE).decode()


def php(container, body):
    return command("docker", "exec", container, "php", "-r",
                   'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
                   '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); ' + body)


original_run = release.ops.run


def real_run(args, **kwargs):
    if args[:3] == ["docker", "image", "inspect"]:
        return json.dumps([{"Config": {"Labels": {}}}])
    if args[:2] == ["docker", "exec"] and args[3:5] == ["php", "-r"]:
        # MySQL identity is tested separately. Keep all actual Laravel/Redis calls intact.
        args = list(args)
        args[-1] = args[-1].replace('echo json_encode',
            'Illuminate\\Support\\Facades\\DB::swap(new class { public function selectOne($sql) '
            '{ return (object)["uuid"=>"synthetic-mysql"]; } }); echo json_encode')
    return original_run(args, **kwargs)


try:
    command("docker", "network", "create", "--internal", network)
    command("docker", "run", "--detach", "--name", redis, "--network", network, "redis:7.4-alpine")
    containers.append(redis)
    for suffix, prefix in (("old", "dlp-friends-database-"), ("current", "dlp_friends_production_")):
        name = identifier + "-" + suffix
        args = ["docker", "run", "--detach", "--name", name, "--network", network]
        for key, value in {"APP_ENV": "production", "APP_KEY": "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
                           "APP_DEBUG": "false", "APP_MAINTENANCE_DRIVER": "cache", "APP_MAINTENANCE_STORE": "redis",
                           "CACHE_STORE": "redis", "CACHE_PREFIX": "dlp_friends_production_cache_",
                           "REDIS_HOST": redis, "REDIS_PREFIX": prefix, "REDIS_CACHE_DB": "1",
                           "DB_DATABASE": "production"}.items():
            args += ["--env", key + "=" + value]
        command(*(args + [image]))
        containers.append(name)
    old, current = containers[1:]
    deadline = time.monotonic() + 30
    while True:
        try:
            command("docker", "exec", current, "curl", "--fail", "--silent", "http://127.0.0.1/up")
            break
        except subprocess.CalledProcessError:
            assert time.monotonic() < deadline, "runtime did not start"
            time.sleep(.2)
    command("docker", "exec", old, "php", "artisan", "down")
    assert php(old, 'echo app()->isDownForMaintenance()?"closed":"open";') == "closed"
    assert php(current, 'echo app()->isDownForMaintenance()?"closed":"open";') == "open"
    gate = object.__new__(release.Release)
    gate.config = {"source": {"web_container": old, "database": "production"}}
    gate.mysql = lambda *args: "synthetic-mysql\tproduction\n"
    gate.redis_identity = lambda side: next(line.split(":", 1)[1].strip() for line in
        command("docker", "exec", redis, "redis-cli", "INFO", "server").splitlines() if line.startswith("run_id:"))
    release.ops.run = real_run
    try:
        gate.runtime()
        raise AssertionError("old maintenance namespace accepted")
    except release.ops.OperationError as error:
        assert str(error) == "persistent_production_maintenance_required"
    gate.config["source"]["web_container"] = current
    command("docker", "exec", current, "php", "artisan", "down")
    assert gate.runtime()["closed"] is True

    command("docker", "exec", "-i", current, "sh", "-c", "cat > public/drain-probe.php", input=(
        b'<?php register_shutdown_function(function () { sleep(2); '
        b'file_put_contents("/tmp/drain-completed", "yes"); }); '
        b'file_put_contents("/tmp/drain-started", "yes"); sleep(3); '
        b'echo "write-completed"; fastcgi_finish_request();'))
    request = subprocess.Popen(["docker", "exec", current, "curl", "--silent", "--include", "http://127.0.0.1/drain-probe.php"],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    deadline = time.monotonic() + 10
    while subprocess.run(["docker", "exec", current, "test", "-f", "/tmp/drain-started"], capture_output=True).returncode:
        assert time.monotonic() < deadline, "request did not start"
        time.sleep(.1)
    # Exercise prepare's real ordering; stop immediately at the export boundary.
    # Candidate attestation and background drainage have separate boundary tests.
    gate.image = lambda *args: {}
    gate.rehearsal = lambda metadata: {"source_schema_sha256": "synthetic-schema"}
    gate.running_image = lambda: "ghcr.io/zdossantos/dlp-friends@sha256:" + "a" * 64
    gate.directory = Path(tempfile.mkdtemp())
    gate.config["source"]["process_containers"] = []
    gate.drained = lambda: None
    gate.artisan = lambda *args: command("docker", "exec", current, "php", "artisan", "down")
    def before_export(side):
        command("docker", "exec", current, "test", "-f", "/tmp/drain-completed")
        gate.http_drained()
        raise release.ops.OperationError("stop_before_export")
    gate.schema_fingerprint = before_export
    try:
        gate.prepare("synthetic-candidate", "synthetic-commit")
        raise AssertionError("prepare passed the export boundary")
    except release.ops.OperationError as error:
        assert str(error) == "stop_before_export"
    finally:
        gate.directory.rmdir()
    stdout, stderr = request.communicate(timeout=10)
    command("docker", "exec", current, "test", "-f", "/tmp/drain-completed")
    assert request.returncode == 0 and b"write-completed" in stdout, (request.returncode, stdout, stderr,
        command("docker", "exec", current, "sh", "-c", "cat /var/log/nginx/error.log /usr/local/var/log/php-fpm.log 2>/dev/null || true"))
    gate.http_drained()
    assert gate.runtime()["closed"] is True  # CLI remains usable with FPM stopped.
finally:
    for name in reversed(containers):
        subprocess.run(["docker", "rm", "--force", name], capture_output=True)
    subprocess.run(["docker", "network", "rm", network], capture_output=True)
