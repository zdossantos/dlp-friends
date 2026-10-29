"""Exercise the real SSH relay with a stateful Docker boundary, never a real daemon."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

scenario = sys.argv[1]
root = Path(__file__).resolve().parents[3]
with tempfile.TemporaryDirectory() as directory:
    path = Path(directory)
    config = {'application_uuid': 'beta-fixed', 'production_application_uuid': 'production-fixed',
              'lock_file': str(path / 'lock'), 'stop_resources': {
                  'application_uuids': ['beta-fixed', 'beta-rolling'], 'mysql_uuid': 'beta-mysql',
                  'redis_uuid': 'beta-redis', 'mailpit_container': 'dlp-friends-beta-mailpit'}}
    if scenario == 'missing-config':
        del config['stop_resources']
    (path / 'config.json').write_text(json.dumps(config))
    (path / 'config.json').chmod(0o600)
    containers = []
    for name, kind, owner, image in [('web-beta', 'application', '11', 'app'),
        ('worker-beta', 'application', '11', 'app'), ('scheduler-beta', 'application', '11', 'app'),
        ('reverb-beta', 'application', '11', 'app'), ('rolling-beta', 'application', '12', 'app'),
        ('beta-mysql', 'database', '21', 'mysql:8.4'), ('beta-redis', 'database', '22', 'redis:7.4'),
        ('production', 'application', '99', 'app'), ('dlp-friends-beta-mailpit', '', '', 'axllent/mailpit:v1')]:
        labels = {'coolify.managed': 'true', 'coolify.' + kind + 'Id': owner} if kind else {}
        containers.append({'Id': name, 'Name': '/' + name, 'Config': {'Image': image, 'Labels': labels},
                           'State': {'Running': scenario != 'cold'}, 'HostConfig': {'PortBindings': {}}})
    containers.append({'Id': 'production-db', 'Name': '/production-db', 'Config': {'Image': 'mysql:8.4',
        'Labels': {'coolify.managed': 'true', 'coolify.databaseId': '21'}},
        'State': {'Running': scenario != 'cold'}, 'HostConfig': {'PortBindings': {}}})
    if scenario == 'wrong-database-owner':
        containers[5]['Config']['Labels']['coolify.databaseId'] = '99'
    if scenario == 'public-mailpit':
        containers[8]['HostConfig']['PortBindings'] = {'8025/tcp': [{}]}
    (path / 'state').write_text(json.dumps(containers))
    (path / 'docker').write_text('''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
p=Path(os.environ['STOP_FIXTURE']); a=sys.argv[1:]; c=json.loads((p/'state').read_text())
if a[0]=='exec':
    if os.environ['STOP_SCENARIO']=='ownership-refused': sys.exit(1)
    print(json.dumps({'applications': {'beta-fixed':'11','beta-rolling':'12'}, 'databases': {'beta-mysql':'21','beta-redis':'22'}}))
elif a[0]=='ps': print(' '.join(x['Id'] for x in c))
elif a[0]=='inspect': print(json.dumps([x for x in c if x['Id'] in a[1:]]))
elif a[0]=='stop':
    with (p/'stops').open('a') as f: f.write(json.dumps(a[3:])+'\\n')
    if os.environ['STOP_SCENARIO']!='stop-failure':
        for x in c:
            if x['Id'] in a[3:]: x['State']['Running']=False
    (p/'state').write_text(json.dumps(c))
else: sys.exit(2)
''')
    (path / 'docker').chmod(0o700)
    result = subprocess.run(['python3', str(root / 'scripts/operations/beta-ssh-deploy.py'), '--config', str(path/'config.json')],
        input=json.dumps({'action':'stop','application_uuid':'beta-fixed'}), text=True, capture_output=True,
        env={**os.environ, 'PATH':directory+':'+os.environ['PATH'], 'STOP_FIXTURE':directory, 'STOP_SCENARIO':scenario})
    if scenario in ('running', 'cold'):
        assert result.returncode == 0, result.stderr
        assert json.loads(result.stdout)['status'] == 'stopped'
        state = json.loads((path/'state').read_text())
        assert all(not x['State']['Running'] for x in state if x['Id'] not in ('production', 'production-db'))
        assert state[7]['State']['Running'] == (scenario != 'cold')
        assert state[9]['State']['Running'] == (scenario != 'cold')
        if scenario == 'running':
            groups = [json.loads(line) for line in (path/'stops').read_text().splitlines()]
            assert groups == [['web-beta','worker-beta','scheduler-beta','reverb-beta','rolling-beta'],
                              ['dlp-friends-beta-mailpit'], ['beta-mysql'], ['beta-redis']], groups
    else:
        assert result.returncode != 0 and not result.stdout
        if scenario != 'stop-failure': assert not (path/'stops').exists()
