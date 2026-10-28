"""Exercise delivery ordering and failures without contacting production."""
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'scripts/operations'))
import operations as ops

SPEC = importlib.util.spec_from_file_location('rolling_release', ROOT / 'scripts/operations/rolling_release.py')
rolling = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(rolling)

IMAGE = 'ghcr.io/zdossantos/dlp-friends@sha256:' + 'a' * 64
REQUEST = {'action': 'deploy', 'version': 'v1.16.2', 'commit': 'b' * 40, 'image': IMAGE}


class FakeRelease(rolling.RollingRelease):
    def __init__(self, directory, fail=None):
        self.directory = Path(directory)
        self.config = {'initial_version': 'v1.16.1', 'release_line': '1.16'}
        self.events = []
        self.fail = fail

    def preflight(self, request):
        self.event('preflight')
        return ['new_migration']

    def event(self, name):
        self.events.append(name)
        ops.require(self.fail != name, name + '_failed')

    def backup(self): self.event('backup')
    def migrate(self, request): self.event('migrate')
    def ready(self, request): self.event('ready')
    def deploy_web(self, request): self.event('web')
    def deploy_support(self, request): self.event('support')


class RollingReleaseTest(unittest.TestCase):
    def test_success_and_duplicate_delivery(self):
        with tempfile.TemporaryDirectory() as directory:
            relay = FakeRelease(directory)
            self.assertEqual(relay.deliver(REQUEST)['status'], 'deployed')
            self.assertEqual(relay.events, ['preflight', 'backup', 'migrate', 'ready', 'web', 'support'])
            relay.events.clear()
            self.assertEqual(relay.deliver(REQUEST)['status'], 'already_deployed')
            self.assertEqual(relay.events, [])

    def test_preflight_never_mutates(self):
        with tempfile.TemporaryDirectory() as directory:
            relay = FakeRelease(directory)
            result = relay.deliver({**REQUEST, 'action': 'preflight'})
            self.assertEqual(result['status'], 'ready')
            self.assertEqual(relay.events, ['preflight'])
            self.assertFalse((Path(directory) / 'rolling-state.json').exists())

    def test_each_failure_blocks_every_later_stage_and_retries(self):
        stages = ['preflight', 'backup', 'migrate', 'ready', 'web', 'support']
        for stage in stages:
            with self.subTest(stage=stage), tempfile.TemporaryDirectory() as directory:
                relay = FakeRelease(directory, stage)
                with self.assertRaises(ops.OperationError): relay.deliver(REQUEST)
                self.assertEqual(relay.events, stages[:stages.index(stage) + 1])
                if stage != 'preflight':
                    with self.assertRaisesRegex(ops.OperationError, 'operator_reconciliation_required'):
                        FakeRelease(directory).deliver(REQUEST)

    def test_rejects_untrusted_requests_before_external_calls(self):
        bad = [dict(REQUEST, image='latest'), dict(REQUEST, commit='main'),
               dict(REQUEST, version='v1.17.0'), dict(REQUEST, version='v1.16.1'),
               dict(REQUEST, command='whoami'), dict(REQUEST, action='rollback')]
        for request in bad:
            with self.subTest(request=request), tempfile.TemporaryDirectory() as directory:
                relay = FakeRelease(directory)
                with self.assertRaises(ops.OperationError): relay.deliver(request)
                self.assertEqual(relay.events, [])

    def test_migrations_must_be_additive_unchanged_and_reviewed(self):
        old = {'migrations': {'old': 'a' * 64}, 'ran': ['old'], 'identity': {'db': 'production'}}
        new = {**old, 'migrations': {**old['migrations'], 'new': 'b' * 64}, 'policy': {'new': 'b' * 64}}
        self.assertEqual(rolling.validate_migrations(old, new), ['new'])
        for modified in [dict(new, policy={}), dict(new, policy={'new': 'c' * 64}),
                         dict(new, migrations={'new': 'b' * 64}),
                         dict(new, migrations={'old': 'c' * 64}), dict(new, identity={'db': 'beta'}),
                         dict(new, ran=[])]:
            with self.subTest(modified=modified), self.assertRaises(ops.OperationError):
                rolling.validate_migrations(old, modified)

    def test_observes_real_coolify_labels_and_docker_grace_period(self):
        with tempfile.TemporaryDirectory() as directory:
            relay = FakeRelease(directory)
            container = {'State': {'Running': True, 'Health': {'Status': 'healthy'}},
                         'Config': {'StopTimeout': 120, 'Image': IMAGE, 'Cmd': ['web']}, 'Image': 'sha256:local'}
            relay.command = Mock(side_effect=['container-id', json.dumps([container])])
            self.assertEqual(relay.healthy({'id': 9, 'uuid': 'webuuid'}, IMAGE), [container])
            self.assertIn('label=coolify.applicationUuid=webuuid', relay.command.call_args_list[0].args[0])

    def test_waits_for_readiness_without_redeploying(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(rolling.time, 'sleep'):
            relay = FakeRelease(directory)
            relay.healthy = Mock(side_effect=[ops.OperationError('healthy_containers_required'), []])
            relay.wait_healthy({'uuid': 'web'}, IMAGE)
            self.assertEqual(relay.healthy.call_count, 2)
            self.assertEqual(relay.events, [])

    def test_no_backup_for_schema_unchanged_release(self):
        with tempfile.TemporaryDirectory() as directory:
            relay = FakeRelease(directory)
            relay.preflight = lambda request: []
            relay.deliver(REQUEST)
            self.assertEqual(relay.events, ['migrate', 'ready', 'web', 'support'])

    def test_cannot_replace_an_already_deployed_version(self):
        with tempfile.TemporaryDirectory() as directory:
            relay = FakeRelease(directory)
            relay.deliver(REQUEST)
            with self.assertRaises(ops.OperationError): relay.deliver(dict(REQUEST, image=IMAGE[:-1] + 'c'))


if __name__ == '__main__': unittest.main()
