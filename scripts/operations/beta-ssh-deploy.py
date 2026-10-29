#!/usr/bin/env python3
"""Root-owned forced SSH command. Input only permits fixed actions on configured beta resources."""
import argparse
import base64
import fcntl
import json
import os
from pathlib import Path
import re
import stat
import signal
import subprocess
import sys


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True)
    args = parser.parse_args()
    os.umask(0o077)
    def interrupted(signum, frame):
        raise RuntimeError('Beta operation interrupted')
    for event in (signal.SIGHUP, signal.SIGTERM):
        signal.signal(event, interrupted)
    config_path = Path(args.config)
    metadata = config_path.lstat()
    if not stat.S_ISREG(metadata.st_mode) or stat.S_IMODE(metadata.st_mode) != 0o600 or metadata.st_uid != os.geteuid():
        raise ValueError('Invalid private configuration')
    config = json.loads(config_path.read_text())
    target = config['application_uuid']
    if not re.fullmatch(r'[a-zA-Z0-9-]+', target) or target == config['production_application_uuid']:
        raise ValueError('Invalid beta target')
    raw = sys.stdin.buffer.read(4097)
    if len(raw) > 4096:
        raise ValueError('Oversized request')
    request = json.loads(raw)
    if not isinstance(request, dict) or request.get('application_uuid') != target:
        raise ValueError('Wrong target')
    if request.get('action') == 'stop' and 'stop_resources' in config:
        if set(request) != {'application_uuid', 'action'}:
            raise ValueError('Invalid stop request')
        from beta_stop import stop_beta
        with open(config['lock_file'], 'a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            print(json.dumps(stop_beta(config)))
        return
    if "operations_config" in config:
        action = request.get('action')
        expected = {'application_uuid', 'action'} if action == 'stop' else {'application_uuid', 'action', 'sha', 'image', 'replace_beta_tests'}
        if action not in ('stop', 'update', 'initialize-production', 'initialize-references') or set(request) != expected:
            raise ValueError('Invalid lifecycle request')
        if action == 'stop':
            from beta_lifecycle import BetaLifecycle
            lifecycle = BetaLifecycle(config['operations_config'], queue_deployment)
            if lifecycle.config['beta']['application_uuid'] != target or lifecycle.config['source']['application_uuid'] != config['production_application_uuid']:
                raise ValueError('Mismatched operations configuration')
            print(json.dumps(lifecycle.execute(request)))
            return
        if type(request['replace_beta_tests']) is not bool:
            raise ValueError('Invalid confirmation')
    elif set(request) != {'application_uuid', 'sha', 'image'}:
        raise ValueError('Invalid fields')
    if not isinstance(request['sha'], str) or not re.fullmatch(r'[a-f0-9]{40}', request['sha']):
        raise ValueError('Invalid commit')
    if not isinstance(request['image'], str) or not re.fullmatch(r'ghcr\.io/zdossantos/dlp-friends@sha256:[a-f0-9]{64}', request['image']):
        raise ValueError('Invalid image')
    if "operations_config" in config:
        from beta_lifecycle import BetaLifecycle
        lifecycle = BetaLifecycle(config["operations_config"], queue_deployment)
        if lifecycle.config["beta"]["application_uuid"] != target or lifecycle.config["source"]["application_uuid"] != config["production_application_uuid"]:
            raise ValueError("Mismatched operations configuration")
        print(json.dumps(lifecycle.execute(request)))
    else:
        if "action" in request:
            raise ValueError("Lifecycle configuration required")
        with open(config['lock_file'], 'a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            print(json.dumps(queue_deployment(request)))


def queue_deployment(request):
    payload = base64.b64encode(json.dumps(request).encode()).decode()
    php = r'''
$r=json_decode(base64_decode('PAYLOAD'),true,512,JSON_THROW_ON_ERROR);
$id=Illuminate\Support\Facades\DB::transaction(function()use($r){
    $a=App\Models\Application::where('uuid',$r['application_uuid'])->lockForUpdate()->firstOrFail();
    if($a->environment->name!=='beta'||$a->git_repository!=='zdossantos/dlp-friends')throw new RuntimeException('Wrong beta application');
    if(App\Models\ApplicationDeploymentQueue::where('application_id',$a->id)->whereIn('status',['queued','in_progress'])->exists())throw new RuntimeException('Deployment already active');
    $a->environment_variables()->updateOrCreate(['key'=>'APP_IMAGE','is_preview'=>false],['value'=>$r['image'],'is_literal'=>true,'is_buildtime'=>true,'is_runtime'=>true]);
    $a->git_commit_sha=$r['sha'];$a->git_branch='chore/beta-candidates/'.$r['sha'];$a->save();
    $id=new_public_id();
    queue_application_deployment(application:$a,deployment_uuid:$id,force_rebuild:false);
    if(!App\Models\ApplicationDeploymentQueue::where(['application_id'=>$a->id,'deployment_uuid'=>$id])->exists())throw new RuntimeException('Deployment not admitted');
    return $id;
});
echo json_encode(['deployment_uuid'=>(string)$id],JSON_THROW_ON_ERROR);
'''.replace('PAYLOAD', payload).replace('\n', ' ')
    result = subprocess.run(
        ['docker', 'exec', '-u', '0', 'coolify', 'php', 'artisan', 'tinker', '--execute=' + php],
        capture_output=True, timeout=60, check=False,
    )
    if result.returncode:
        raise ValueError('Coolify rejected the deployment')
    response = json.loads(result.stdout)
    deployment = response.get('deployment_uuid')
    if not isinstance(deployment, str) or not re.fullmatch(r'[a-zA-Z0-9-]+', deployment):
        raise ValueError('Invalid Coolify response')
    return {'deployment_uuid': deployment}


if __name__ == '__main__':
    try:
        main()
    except (Exception, KeyboardInterrupt):
        # Coolify errors may contain runtime configuration. Never relay them to CI.
        print('Beta deployment refused; inspect the server configuration.', file=sys.stderr)
        sys.exit(1)
