"""Observable lifecycle ordering with synthetic server boundaries."""
from pathlib import Path
import sys
import tempfile
import os
os.umask(0o077)
import contextlib
import json
sys.path.insert(0, str(Path(__file__).resolve().parents[3] / 'scripts/operations'))
import beta_lifecycle as beta
import operations as ops

scenario = sys.argv[1]
with tempfile.TemporaryDirectory() as directory:
    operation = object.__new__(beta.BetaLifecycle)
    operation.directory = Path(directory)
    operation.config = {'beta': {'web_container': 'beta-web', 'process_containers': ['worker', 'scheduler', 'reverb'], 'application_uuid': 'beta'}, 'source': {'application_uuid': 'production'}}
    events = []
    operation.lock = contextlib.nullcontext
    operation.resources = lambda: events.append('validate-resources')
    operation.image = lambda *args: events.append('verify-image')
    operation.dependencies = lambda action: events.append('dependencies-' + action)
    operation.application_stop = lambda: events.append('stop-application')
    @contextlib.contextmanager
    def temporary(image):
        events.append('temporary-start')
        try:
            yield
        finally:
            events.append('temporary-remove')
    operation.temporary = temporary
    operation.close = lambda: events.append('maintenance-down')
    operation.validate_targets = lambda: events.append('validate-targets')
    operation._refresh = lambda mode: events.append('copy-' + mode)
    operation.bootstrap = lambda image: events.append('bootstrap-admin')
    def command(image, *args):
        events.append(args[0])
        if scenario == 'migration-failure' and args[0] == 'migrate':
            raise ops.OperationError('synthetic_failure')
    operation.beta_command = command
    operation.queue = lambda request: events.append('queue') or {'deployment_uuid': 'deployment'}
    def wait(deployment):
        events.append('wait-deployment')
        if scenario == 'deploy-failure':
            raise ops.OperationError('synthetic_failure')
    operation.wait_deployment = wait
    operation.api = lambda *args: events.append('cancel-deployment') or {'status': 'in_progress'}
    operation.verify_deployed = lambda image: events.append('verify-deployed')
    operation.open = lambda: events.append('maintenance-up')
    request = {'action': 'update', 'image': 'immutable', 'sha': 'commit', 'application_uuid': 'beta', 'replace_beta_tests': False}
    if scenario in ('production-copy', 'references-copy', 'no-confirmation', 'failed-initialization'):
        request['action'] = 'initialize-references' if scenario == 'references-copy' else 'initialize-production'
        request['replace_beta_tests'] = scenario != 'no-confirmation'
    state = {'initialized': True, 'status': 'stopped', 'image': 'previous'}
    if scenario == 'failed-initialization':
        state['initialized'] = False
        request['action'] = 'update'
    if scenario == 'stop':
        request = {'action': 'stop', 'application_uuid': 'beta'}
    ops.write_json(operation.directory / 'beta-lifecycle.json', state)
    try:
        result = operation.execute(request)
        assert scenario not in ('no-confirmation', 'failed-initialization', 'migration-failure', 'deploy-failure')
        state = json.loads((operation.directory / 'beta-lifecycle.json').read_text())
        if scenario == 'stop':
            assert state['status'] == 'stopped' and state['initialized']
            assert events == ['validate-resources', 'stop-application', 'dependencies-stop']
        else:
            assert state['status'] == 'ready' and state['initialized']
            assert events.index('maintenance-down') < events.index('migrate') < events.index('queue')
            assert events.index('deployment:check') < events.index('maintenance-up')
            assert events.index('verify-deployed') < events.index('maintenance-up')
            assert events.index('wait-deployment') < events.index('maintenance-up')
            if scenario == 'references-copy':
                assert events.index('copy-references') < events.index('bootstrap-admin') < events.index('queue')
            elif scenario == 'production-copy':
                assert 'copy-production' in events and 'bootstrap-admin' not in events
            else:
                assert not any(event.startswith('copy-') for event in events)
    except ops.OperationError:
        assert scenario in ('no-confirmation', 'failed-initialization', 'migration-failure', 'deploy-failure'), events
        assert 'maintenance-up' not in events
        if scenario in ('migration-failure', 'deploy-failure'):
            assert events[-1] == 'stop-application', events
            assert json.loads((operation.directory / 'beta-lifecycle.json').read_text())['status'] == 'failed'
        else:
            assert not events, events
print('lifecycle_verified')
