"""Exercise resource ownership and non-destructive resource endpoints."""
import sys
import json
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[3] / 'scripts/operations'))
import beta_lifecycle as beta
import operations as ops
scenario = sys.argv[1]
operation = object.__new__(beta.BetaLifecycle)
operation.config = {}
for side in ('source', 'beta'):
    operation.config[side] = {key: side + '-' + key.replace('_', '-') for key in ('application_uuid', 'mysql_uuid', 'redis_uuid', 'mailpit_uuid', 'web_container', 'mysql_container', 'redis_container', 'mailpit_container')}
    operation.config[side]['process_containers'] = [side + '-' + process for process in ('worker', 'scheduler', 'reverb')]
target = operation.config['beta']
if scenario == 'resource-alias':
    target['redis_uuid'] = operation.config['source']['redis_uuid']
owners = {target[key]: {'kind': kind, 'id': str(index)} for index, (key, kind) in enumerate([('application_uuid','application'), ('mysql_uuid','database'), ('redis_uuid','database'), ('mailpit_uuid','service')])}
called = []
def run(arguments, **kwargs):
    called.append(arguments)
    assert arguments[:7] == ['docker','exec','-u','0','coolify','php','artisan']
    import subprocess
    lint = subprocess.run(['php','-l'], input='<?php '+arguments[-1].split('--execute=',1)[1], text=True, capture_output=True)
    assert lint.returncode == 0, lint.stderr
    return json.dumps({'owners': owners})
ops.run = run
operation.exists = lambda name: True
expected = {target['web_container']: target['application_uuid'], **{name: target['application_uuid'] for name in target['process_containers']}, target['mysql_container']: target['mysql_uuid'], target['redis_container']: target['redis_uuid'],target['mailpit_container']:target['mailpit_uuid']}
def inspect(name):
    resource = expected[name]
    kind = owners[resource]['kind']
    labels = {'coolify.managed':'true', 'coolify.'+kind+('Id' if scenario == 'legacy-labels' else 'Uuid'): owners[resource]['id'] if scenario == 'legacy-labels' else resource}
    if scenario == 'container-alias':
        labels['coolify.'+kind+'Uuid'] = 'production'
    return {'Config': {'Labels':labels}}
operation.inspect = inspect
try:
    operation.resources()
    assert scenario in ('valid', 'legacy-labels')
except ops.OperationError:
    assert scenario in ('resource-alias', 'container-alias')
    if scenario == 'resource-alias':
        assert not called

# No volume/network pruning; stop Redis last.
calls = []
operation.api = lambda path, method: calls.append((path, method)) or {}
operation.exists = lambda name: False
operation.wait_containers = lambda *args: None
operation.application_stop()
operation.dependencies('stop')
assert calls == [('/applications/beta-application-uuid/stop?docker_cleanup=false','POST'),('/services/beta-mailpit-uuid/stop?docker_cleanup=false','POST'),('/databases/beta-mysql-uuid/stop?docker_cleanup=false','POST'),('/databases/'+target['redis_uuid']+'/stop?docker_cleanup=false','POST')]
print('resources_verified')
