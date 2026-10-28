#!/usr/bin/env python3
"""Bounded release relay. No maintenance, database rollback or dependency restarts."""
import argparse
import fcntl
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
import urllib.request
import uuid

import operations as ops
from release import Release

REPOSITORY = 'ghcr.io/zdossantos/dlp-friends'
IMAGE_PATTERN = re.escape(REPOSITORY) + r'@sha256:[a-f0-9]{64}'


def version(value):
    ops.require(isinstance(value, str) and re.fullmatch(r'v[1-9][0-9]*\.[0-9]+\.[0-9]+', value), 'stable_version_required')
    return tuple(int(part) for part in value[1:].split('.'))


def validate_migrations(old, new):
    ops.require(old['identity'] == new['identity'], 'runtime_identity_changed')
    ops.require(set(old['ran']) == set(new['ran']), 'migration_register_changed')
    ops.require(all(new['migrations'].get(name) == digest for name, digest in old['migrations'].items()),
                'historical_migration_changed')
    ops.require(set(old['migrations']) <= set(old['ran']), 'old_runtime_has_pending_migrations')
    pending = sorted(set(new['migrations']) - set(new['ran']))
    ops.require(not pending or isinstance(new.get('policy'), dict), 'reviewed_compatible_migration_required')
    ops.require(all(re.fullmatch(r'[a-f0-9]{64}', new['migrations'][name])
                    and new.get('policy', {}).get(name) == new['migrations'][name] for name in pending),
                'reviewed_compatible_migration_required')
    return pending


class RollingRelease:
    def __init__(self, config_path):
        self.config = json.loads(ops.private_file(config_path).read_text())
        self.directory = Path(self.config['state_dir'])
        ops.require(self.directory.is_absolute() and self.directory.is_dir()
                    and self.directory.stat().st_uid == os.geteuid()
                    and self.directory.stat().st_mode & 0o777 == 0o700, 'private_state_directory_required')
        ops.private_file(self.config['coolify_curl_config'])
        ops.private_file(self.config['mysql_client'])
        # The env file is a root-private directory bind used by the existing native web.
        env = Path(self.config['env_file'])
        ops.require(env.is_absolute() and env.is_file() and not env.is_symlink()
                    and env.stat().st_uid == 0 and env.parent.stat().st_mode & 0o077 == 0, 'private_runtime_env_required')
        for key in ('web_uuid', 'support_uuid'):
            ops.require(re.fullmatch(r'[a-z0-9]+', self.config[key]), 'invalid_resource')
        ops.require(self.config['web_uuid'] != self.config['support_uuid'], 'distinct_resources_required')
        ops.require(self.config['environment'] in ('production', 'beta'), 'invalid_environment')
        self.probe = Path(__file__).with_name('rolling-probe.php').read_text()
        self.web = None
        self.support = None

    coolify = Release.coolify

    def command(self, args, *, input=None, timeout=60, output=None):
        try:
            result = subprocess.run(args, input=input, stdout=output or subprocess.PIPE,
                                    stderr=subprocess.PIPE, timeout=timeout, check=False)
        except subprocess.TimeoutExpired:
            raise ops.OperationError('external_operation_timeout') from None
        ops.require(result.returncode == 0, 'external_operation_failed')
        return result.stdout.decode() if output is None else ''

    def containers(self, resource):
        names = self.command(['docker', 'ps', '-q', '--filter', 'label=coolify.applicationUuid=' + resource['uuid']]).split()
        ops.require(names, 'running_resource_required')
        return json.loads(self.command(['docker', 'inspect', *names]))

    def healthy(self, resource, expected_image=None, support=False):
        containers = self.containers(resource)
        ops.require(len(containers) == (3 if support else 1), 'unexpected_running_topology')
        commands = set()
        for container in containers:
            ops.require(container['State']['Running'] and container['State'].get('Health', {}).get('Status') == 'healthy',
                        'healthy_containers_required')
            ops.require(container['Config'].get('StopTimeout', 0) >= 120, 'graceful_stop_required')
            if expected_image and container['Config']['Image'] != expected_image:
                expected_id = json.loads(self.command(['docker', 'image', 'inspect', expected_image]))[0]['Id']
                ops.require(container['Image'] == expected_id, 'running_image_mismatch')
            commands.add(tuple(container['Config']['Cmd']))
        ops.require(commands == ({('worker',), ('scheduler',), ('reverb',)} if support else {('web',)}),
                    'unexpected_processes')
        return containers

    def wait_healthy(self, resource, image, support=False):
        deadline = time.monotonic() + 180
        while True:
            try:
                self.healthy(resource, image, support)
                return
            except ops.OperationError:
                if time.monotonic() >= deadline:
                    raise
                time.sleep(5)

    def public_ready(self):
        host = 'dlp-friends.fr' if self.config['environment'] == 'production' else 'beta.dlp-friends.fr'
        with urllib.request.urlopen('https://' + host + '/ready', timeout=10) as response:
            ops.require(response.status == 200, 'public_web_not_ready')

    def probe_image(self, image, action='inspect'):
        name = 'dlp-rolling-' + uuid.uuid4().hex
        try:
            return self.command(['docker', 'run', '--rm', '--name', name, '-i', '--network', self.config['network'],
                                 '--memory', '384m', '--cpus', '0.5', '--mount',
                                 'type=bind,src=' + self.config['env_file'] + ',dst=/var/www/html/.env,readonly',
                                 '--entrypoint', 'php', image, '--', action],
                                input=self.probe.encode(), timeout=180)
        finally:
            # A timed-out command must not leave a migration silently running in Docker.
            if self.command(['docker', 'ps', '-aq', '--filter', 'name=^/' + name + '$']).strip():
                self.command(['docker', 'rm', '-f', name], timeout=30)

    def metadata(self, request):
        if self.config['environment'] == 'beta':
            # The operator pins the CI-built beta image; no production client endpoints in beta.
            return json.loads(ops.private_file(self.directory / 'rehearsal-image.json').read_text())
        with urllib.request.urlopen('https://github.com/zdossantos/dlp-friends/releases/download/'
                                    + request['version'] + '/container-image.json', timeout=30) as response:
            return json.load(response)

    def preflight(self, request):
        metadata = self.metadata(request)
        ops.require(all(metadata.get(key) == request[key] for key in ('version', 'commit', 'image'))
                    and metadata.get('platform') == 'linux/amd64', 'published_release_mismatch')
        self.command(['docker', 'pull', '--platform', 'linux/amd64', request['image']], timeout=600)
        image = json.loads(self.command(['docker', 'image', 'inspect', request['image']]))[0]
        labels = image['Config'].get('Labels') or {}
        ops.require(image['Os'] == 'linux' and image['Architecture'] == 'amd64'
                    and labels.get('org.opencontainers.image.revision') == request['commit']
                    and labels.get('org.opencontainers.image.version') == request['version']
                    and labels.get('io.dlp-friends.source-tree') == metadata.get('tree')
                    and labels.get('io.dlp-friends.migrations-sha256') == metadata.get('migrations_sha256'),
                    'image_metadata_mismatch')
        self.web = self.coolify('GET', '/applications/' + self.config['web_uuid'])
        self.support = self.coolify('GET', '/applications/' + self.config['support_uuid'])
        ops.require(self.web['uuid'] == self.config['web_uuid'] and self.support['uuid'] == self.config['support_uuid']
                    and self.web['environment_id'] == self.support['environment_id'] == self.config['environment_id'],
                    'resource_identity_changed')
        ops.require(self.web['build_pack'] == 'dockerimage' and self.web.get('health_check_enabled') is True
                    and self.web.get('health_check_path') == '/ready'
                    and self.web.get('ports_exposes') == '80' and not self.web.get('ports_mappings')
                    and not self.web.get('custom_docker_run_options')
                    and not self.web.get('pre_deployment_command') and not self.web.get('post_deployment_command'),
                    'native_rolling_web_required')
        ops.require(self.support['build_pack'] == 'dockercompose'
                    and self.support.get('docker_compose_location') == self.config['support_compose']
                    and self.support.get('git_commit_sha') == self.config['support_commit']
                    and not self.support.get('pre_deployment_command') and not self.support.get('post_deployment_command'),
                    'pinned_support_topology_required')
        web = self.healthy(self.web)[0]
        support = self.healthy(self.support, support=True)
        ops.require(shutil.disk_usage(self.directory).free >= 4 * 1024**3, 'insufficient_disk_headroom')
        memory = Path('/proc/meminfo').read_text()
        available = int(re.search(r'MemAvailable:\s+(\d+)', memory)[1]) * 1024
        ops.require(available >= 1024**3, 'insufficient_memory_headroom')
        old = json.loads(self.command(['docker', 'exec', '-i', web['Id'], 'php', '--', 'inspect'], input=self.probe.encode()))
        identity = old['identity']
        ops.require(identity['environment'] == self.config['environment']
                    and identity['server'] == self.config['mysql_server_uuid']
                    and identity['database'] == self.config['database'], 'unexpected_database_identity')
        for process in support:
            observed = json.loads(self.command(['docker', 'exec', '-i', process['Id'], 'php', '--', 'inspect'], input=self.probe.encode()))
            ops.require(observed['identity'] == identity, 'support_configuration_drift')
        candidate = json.loads(self.probe_image(request['image']))
        pending = validate_migrations(old, candidate)
        self.candidate = candidate
        self.old = old
        return pending

    def backup(self):
        client = ops.private_file(self.config['mysql_client'])
        ops.require(re.fullmatch(r'[A-Za-z0-9_]+', self.config['database']), 'invalid_database')
        identity = self.command(['mysql', '--defaults-extra-file=' + str(client), '--batch', '--skip-column-names',
                                 '--database=' + self.config['database'], '--execute=SELECT @@server_uuid, DATABASE()']).strip().split('\t')
        ops.require(identity == [self.old['identity']['server'], self.old['identity']['database']],
                    'backup_database_identity_mismatch')
        path = self.directory / ('before-' + str(time.time_ns()) + '.sql')
        with path.open('xb') as output:
            os.chmod(path, 0o600)
            self.command(['mysqldump', '--defaults-extra-file=' + str(client), '--single-transaction',
                          '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF',
                          '--default-character-set=utf8mb4', self.config['database']], output=output, timeout=300)
            output.flush()
            os.fsync(output.fileno())
        ops.require(path.stat().st_size > 0, 'empty_backup')

    def migrate(self, request):
        self.probe_image(request['image'], 'migrate')

    def ready(self, request):
        result = json.loads(self.probe_image(request['image']))
        ops.require(result['identity'] == self.old['identity'] and result['migrations'] == self.candidate['migrations']
                    and set(result['migrations']) <= set(result['ran']), 'candidate_not_ready')
        self.healthy(self.web)

    def deploy_resource(self, resource):
        result = self.coolify('POST', '/deploy', {'uuid': resource['uuid'], 'force': False})
        deployments = result.get('deployments', [])
        ops.require(len(deployments) == 1 and deployments[0].get('resource_uuid') == resource['uuid']
                    and re.fullmatch(r'[A-Za-z0-9_-]+', deployments[0].get('deployment_uuid', '')), 'deployment_not_confirmed')
        deployment = deployments[0]['deployment_uuid']
        deadline = time.monotonic() + 900
        while time.monotonic() < deadline:
            status = self.coolify('GET', '/deployments/' + deployment)
            ops.require(status.get('application_id') == resource['id'], 'deployment_resource_mismatch')
            if status.get('status') == 'finished':
                return
            ops.require(status.get('status') not in ('failed', 'cancelled', 'cancelled-by-user'), 'deployment_failed')
            time.sleep(5)
        raise ops.OperationError('deployment_status_unknown')

    def deploy_web(self, request):
        self.coolify('PATCH', '/applications/' + self.web['uuid'], {
            'docker_registry_image_name': REPOSITORY,
            'docker_registry_image_tag': 'sha256-' + request['image'].split('sha256:')[1]})
        self.deploy_resource(self.web)
        self.wait_healthy(self.web, request['image'])
        self.public_ready()

    def deploy_support(self, request):
        self.coolify('PATCH', '/applications/' + self.support['uuid'] + '/envs/bulk', {'data': [{
            'key': 'APP_IMAGE', 'value': request['image'], 'is_preview': False,
            'is_literal': True, 'is_buildtime': True, 'is_runtime': True}]})
        self.deploy_resource(self.support)
        self.wait_healthy(self.support, request['image'], support=True)
        self.healthy(self.web, request['image'])
        self.public_ready()

    def deliver(self, request):
        ops.require(isinstance(request, dict) and set(request) == {'action', 'version', 'commit', 'image'}, 'invalid_request')
        ops.require(request['action'] in ('preflight', 'deploy'), 'invalid_action')
        ops.require(isinstance(request['image'], str) and re.fullmatch(IMAGE_PATTERN, request['image']), 'immutable_image_required')
        ops.require(isinstance(request['commit'], str) and re.fullmatch(r'[a-f0-9]{40}', request['commit']), 'exact_commit_required')
        candidate = version(request['version'])
        ops.require('.'.join(map(str, candidate[:2])) == self.config['release_line'], 'inactive_release_line')
        state_path = self.directory / 'rolling-state.json'
        state = json.loads(ops.private_file(state_path).read_text()) if state_path.exists() else {}
        ops.require(not state or state['status'] == 'deployed', 'operator_reconciliation_required')
        if state and state['version'] == request['version']:
            ops.require(all(state[key] == request[key] for key in ('image', 'commit')), 'version_already_consumed')
            return {'status': 'already_deployed', 'version': request['version']}
        ops.require(candidate > version(state.get('version', self.config['initial_version'])), 'newer_version_required')
        pending = self.preflight(request)
        if request['action'] == 'preflight':
            return {'status': 'ready', 'version': request['version'], 'pending_migrations': len(pending)}
        # Consume before mutations. Unknown outcomes require an operator, never a blind retry.
        report = {**request, 'status': 'in_progress', 'phase': 'backup', 'started_at': int(time.time())}
        os.umask(0o077)
        ops.write_json(state_path, report)
        try:
            if pending:
                self.backup()
            for phase, operation in [('migrate', self.migrate), ('ready', self.ready),
                                     ('web', self.deploy_web), ('support', self.deploy_support)]:
                report['phase'] = phase
                ops.write_json(state_path, report)
                operation(request)
            report.update(status='deployed', completed_at=int(time.time()))
            ops.write_json(state_path, report)
        except Exception:
            report['status'] = 'reconciliation_required'
            ops.write_json(state_path, report)
            raise
        return {'status': 'deployed', 'version': request['version']}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True)
    args = parser.parse_args()
    os.umask(0o077)
    relay = RollingRelease(args.config)
    payload = sys.stdin.buffer.read(4097)
    ops.require(len(payload) <= 4096, 'request_too_large')
    with (relay.directory / 'rolling.lock').open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise ops.OperationError('deployment_already_running') from None
        print(json.dumps(relay.deliver(json.loads(payload))))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        # SQL, env, subprocess and API bodies must never reach GitHub logs.
        print(json.dumps({'status': 'failed', 'reason': 'release_blocked_check_private_state'}))
        sys.exit(1)
