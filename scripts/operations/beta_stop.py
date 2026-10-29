"""Stop the configured beta without requiring production-copy credentials."""
import base64
import json
import re
import subprocess


def docker(*arguments):
    return subprocess.run(['docker', *arguments], capture_output=True, text=True, timeout=180, check=True).stdout


def stop_beta(config):
    resources = config['stop_resources']
    applications = resources['application_uuids']
    databases = [resources['mysql_uuid'], resources['redis_uuid']]
    identifiers = [*applications, *databases]
    if (not applications or config['application_uuid'] not in applications
            or len(set(identifiers)) != len(identifiers)
            or config['production_application_uuid'] in identifiers
            or not all(isinstance(value, str) and re.fullmatch(r'[A-Za-z0-9-]+', value) for value in identifiers)):
        raise ValueError('Invalid stop resources')
    mailpit = resources['mailpit_container']
    if not isinstance(mailpit, str) or not re.fullmatch(r'dlp-friends-beta-mailpit', mailpit):
        raise ValueError('Invalid private Mailpit')
    payload = base64.b64encode(json.dumps({**resources, 'production': config['production_application_uuid']}).encode()).decode()
    # Resolve actual ownership and refuse active deployments before stopping anything.
    php = r'''
$r=json_decode(base64_decode('PAYLOAD'),true,512,JSON_THROW_ON_ERROR);
$p=App\Models\Application::where('uuid',$r['production'])->firstOrFail();
$a=App\Models\Application::where('uuid',$r['application_uuids'][0])->firstOrFail();
if($a->environment->name!=='beta'||$a->environment_id===$p->environment_id||$a->environment->project_id!==$p->environment->project_id)throw new RuntimeException('Invalid beta');
$owners=['applications'=>[],'databases'=>[]];
foreach($r['application_uuids']as$uuid){
$x=App\Models\Application::where('uuid',$uuid)->firstOrFail();
if($x->environment_id!==$a->environment_id||$x->destination_id!==$a->destination_id||$x->git_repository!=='zdossantos/dlp-friends')throw new RuntimeException('Shared application');
if(App\Models\ApplicationDeploymentQueue::where('application_id',$x->id)->whereIn('status',['queued','in_progress'])->exists())throw new RuntimeException('Busy');
$owners['applications'][$uuid]=(string)$x->id;
}
foreach(['mysql'=>App\Models\StandaloneMysql::class,'redis'=>App\Models\StandaloneRedis::class]as$kind=>$class){
$x=$class::where('uuid',$r[$kind.'_uuid'])->firstOrFail();
if($x->environment_id!==$a->environment_id||$x->destination_id!==$a->destination_id||$x->is_public)throw new RuntimeException('Shared database');
$owners['databases'][$x->uuid]=(string)$x->id;
}
echo json_encode($owners,JSON_THROW_ON_ERROR);
'''.replace('PAYLOAD', payload)
    owners = json.loads(docker('exec', '-u', '0', 'coolify', 'php', 'artisan', 'tinker', '--execute=' + php))
    if set(owners['applications']) != set(applications) or set(owners['databases']) != set(databases):
        raise ValueError('Invalid ownership response')
    def containers():
        ids = docker('ps', '-aq').split()
        return json.loads(docker('inspect', *ids)) if ids else []
    groups = [[], [], [], []]
    for container in containers():
        name = container['Name'].lstrip('/')
        labels = container['Config'].get('Labels') or {}
        group = None
        for kind, field in [('application', 'applications'), ('database', 'databases')]:
            for uuid, owner in owners[field].items():
                matches = labels.get('coolify.' + kind + 'Uuid') == uuid or (
                    labels.get('coolify.' + kind + 'Id') == owner and (kind == 'application' or name == uuid))
                if matches:
                    if labels.get('coolify.managed') != 'true':
                        raise ValueError('Unmanaged beta resource')
                    group = 0 if kind == 'application' else 2 + databases.index(uuid)
        if name in databases and group != 2 + databases.index(name):
            raise ValueError('Database owner mismatch')
        if name == mailpit:
            if (group is not None or not container['Config']['Image'].startswith('axllent/mailpit:')
                    or container['HostConfig'].get('PortBindings')):
                raise ValueError('Invalid private Mailpit container')
            group = 1
        if group is not None and container['State']['Running']:
            groups[group].append(container['Id'])
    for group in groups:
        if group:
            # Graceful SIGTERM flushes MySQL and Redis AOF; no rm, down or volume cleanup.
            docker('stop', '--timeout', '60', *group)
            if any(item['State']['Running'] for item in json.loads(docker('inspect', *group))):
                raise ValueError('Beta container still running')
    return {'status': 'stopped'}
