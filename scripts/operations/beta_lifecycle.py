"""Fixed beta lifecycle actions, executed only by the private forced SSH relay."""
import base64
import contextlib
import json
import os
import re
import secrets
import time
import uuid

import operations as ops
from release import Release


class BetaLifecycle(Release):
    def __init__(self, config, queue):
        super().__init__(config)
        self.queue = queue
        ops.private_file(self.config['coolify_curl_config'])

    def api(self, path, method='GET'):
        endpoint = self.config['coolify_api_url'].rstrip('/')
        ops.require(endpoint.startswith('https://') or endpoint.startswith('http://127.0.0.1:'), 'private_api_transport_required')
        return json.loads(ops.run(['curl', '--config', self.config['coolify_curl_config'], '--silent', '--fail',
                                  '--connect-timeout', '10', '--max-time', '60', '--request', method, endpoint + path]))

    def resources(self):
        source, target = self.config['source'], self.config['beta']
        names = ('application_uuid', 'mysql_uuid', 'redis_uuid', 'mailpit_uuid')
        ids = [target[key] for key in names]
        ops.require(len(set(ids)) == 4 and all(re.fullmatch(r'[A-Za-z0-9-]+', value) for value in ids), 'invalid_beta_resources')
        ops.require(set(ids).isdisjoint(source[key] for key in names if key in source), 'resource_alias')
        payload = base64.b64encode(json.dumps({'beta': {key: target[key] for key in names}, 'source': {key: source[key] for key in ('mysql_uuid','redis_uuid')}}).encode()).decode()
        # Validate the actual Coolify ownership, not names supplied by the SSH caller.
        php = r'''
$payload=json_decode(base64_decode('PAYLOAD'),true,512,JSON_THROW_ON_ERROR);
$r=$payload['beta'];
$a=App\Models\Application::where('uuid',$r['application_uuid'])->firstOrFail();
if($a->environment->name!=='beta'||$a->git_repository!=='zdossantos/dlp-friends')throw new RuntimeException('Invalid beta');
if(App\Models\ApplicationDeploymentQueue::where('application_id',$a->id)->whereIn('status',['queued','in_progress'])->exists())throw new RuntimeException('Busy');
$m=App\Models\StandaloneMysql::where('uuid',$r['mysql_uuid'])->firstOrFail();
$d=App\Models\StandaloneRedis::where('uuid',$r['redis_uuid'])->firstOrFail();
$s=App\Models\Service::where('uuid',$r['mailpit_uuid'])->firstOrFail();
foreach([$m,$d,$s]as$x)if($x->environment_id!==$a->environment_id||$x->destination_id!==$a->destination_id)throw new RuntimeException('Shared resource');
if($m->is_public||$d->is_public)throw new RuntimeException('Public database');
if($s->applications()->count()!==1||$s->databases()->exists())throw new RuntimeException('Shared service');
$mail=$s->applications()->firstOrFail();
if(!str_starts_with($mail->image,'axllent/mailpit:')||$mail->fqdn||$mail->ports)throw new RuntimeException('Public or invalid Mailpit');
$storages=[];
foreach(['source','beta']as$side)foreach(['mysql'=>App\Models\StandaloneMysql::class,'redis'=>App\Models\StandaloneRedis::class]as$kind=>$class){
$db=$class::where('uuid',$payload[$side][$kind.'_uuid'])->firstOrFail();
$mount=$kind==='mysql'?'/var/lib/mysql':'/data';
$volumes=$db->persistentStorages()->where('mount_path',$mount)->get();
if($volumes->count()!==1)throw new RuntimeException('Missing persistent volume');
$volume=$volumes->first();$storages[$side][$kind]=['name'=>$volume->name,'host_path'=>$volume->host_path];
}
echo json_encode(['storages'=>$storages,'owners'=>[$r['application_uuid']=>['kind'=>'application','id'=>(string)$a->id],$r['mysql_uuid']=>['kind'=>'database','id'=>(string)$m->id],$r['redis_uuid']=>['kind'=>'database','id'=>(string)$d->id],$r['mailpit_uuid']=>['kind'=>'service','id'=>(string)$s->id]]],JSON_THROW_ON_ERROR);
'''.replace('PAYLOAD', payload)
        result = json.loads(ops.run(['docker', 'exec', '-u', '0', 'coolify', 'php', 'artisan', 'tinker', '--execute=' + php]))
        ops.require(set(result.get('owners', {})) == set(ids), 'beta_resource_identity_required')
        # Coolify removes stopped containers; inspect its planned storage before any start or SAVE.
        volumes = {}
        for side in ('source', 'beta'):
            volumes[side] = []
            for key, destination in (('mysql', '/var/lib/mysql'), ('redis', '/data')):
                storage = result['storages'][side][key]
                path = storage['host_path']
                if not path:
                    ops.require(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*', storage['name']), 'invalid_volume_name')
                    volume = json.loads(ops.run(['docker', 'volume', 'inspect', storage['name']]))[0]
                    path = volume['Mountpoint']
                ops.require(isinstance(path, str) and path.startswith('/'), 'persistent_volume_required')
                path = os.path.realpath(path)
                volumes[side].append(path)
                container = self.config[side][key + '_container']
                if self.exists(container):
                    mounted = [os.path.realpath(mount['Source']) for mount in self.inspect(container)['Mounts']
                               if mount['Destination'] == destination]
                    ops.require(mounted == [path], 'planned_volume_mismatch')
        ops.require(len(set(volumes['beta'])) == 2 and not any(
            a == b or a.startswith(b.rstrip('/') + '/') or b.startswith(a.rstrip('/') + '/')
            for a in volumes['source'] for b in volumes['beta']), 'volume_alias')
        # Every named container must also carry the matching Coolify resource label.
        expected = [(target['web_container'], target['application_uuid']),
                    *((name, target['application_uuid']) for name in target['process_containers']),
                    (target['mysql_container'], target['mysql_uuid']), (target['redis_container'], target['redis_uuid']),
                    (target['mailpit_container'], target['mailpit_uuid'])]
        source_names = {source['web_container'], *source['process_containers'], source['mysql_container'], source['redis_container'], source.get('mailpit_container')}
        ops.require(len({name for name, _ in expected}) == 7 and not source_names.intersection(name for name, _ in expected), 'container_alias')
        for name, resource in expected:
            if self.exists(name):
                labels = self.inspect(name)['Config']['Labels']
                owner = result['owners'][resource]
                label = 'coolify.' + owner['kind']
                actual = labels.get(label + 'Uuid')
                if actual is None and labels.get(label + 'Id') == owner['id']:
                    actual = resource
                ops.require(labels.get('coolify.managed') == 'true' and actual == resource, 'container_resource_mismatch')

    def exists(self, name):
        ops.require(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*', name), 'invalid_container_name')
        return bool(ops.run(['docker', 'ps', '-aq', '--filter', 'name=^/' + name + '$']).strip())

    def wait_containers(self, containers, running):
        deadline = time.monotonic() + 300
        while time.monotonic() < deadline:
            ready = True
            for container in containers:
                exists = self.exists(container)
                if not exists:
                    ready = ready and not running
                    continue
                state = self.inspect(container)['State']
                ready = ready and state['Running'] == running
                if running:
                    ready = ready and state.get('Health', {}).get('Status', 'healthy') == 'healthy'
            if ready:
                return
            time.sleep(2)
        raise ops.OperationError('beta_resource_timeout')

    def application_stop(self):
        self.api('/applications/' + self.config['beta']['application_uuid'] + '/stop?docker_cleanup=false', 'POST')
        self.wait_containers([self.config['beta']['web_container'], *self.config['beta']['process_containers']], False)

    def dependencies(self, action):
        target = self.config['beta']
        # Redis is last to stop, first to start: maintenance survives in its volume.
        keys = ('redis', 'mysql', 'mailpit') if action == 'start' else ('mailpit', 'mysql', 'redis')
        for key in keys:
            kind = 'services' if key == 'mailpit' else 'databases'
            suffix = '?docker_cleanup=false' if action == 'stop' else ''
            if key == 'redis' and action == 'stop' and self.exists(target['redis_container']) and self.inspect(target['redis_container'])['State']['Running']:
                password = ops.private_file(target['redis_password_file']).read_bytes().rstrip(b'\n') + b'\n'
                ops.run(['docker', 'exec', '-i', target['redis_container'], 'sh', '-c',
                         'IFS= read -r REDISCLI_AUTH; export REDISCLI_AUTH; exec redis-cli SAVE'], input=password)
            self.api('/' + kind + '/' + target[key + '_uuid'] + '/' + action + suffix, 'POST')
            self.wait_containers([target[key + '_container']], action == 'start')

    @contextlib.contextmanager
    def temporary(self, image):
        target = self.config['beta']
        original = target['web_container']
        name = 'dlp-beta-operations-' + uuid.uuid4().hex
        private = ops.private_file(target['env_file'])
        # Same image/configuration, no HTTP listener and no background process.
        ops.run(['docker', 'run', '-d', '--memory', '192m', '--cpus', '0.5', '--name', name, '--network', target['network'], '--env-file', str(private),
                 '--entrypoint', 'sleep', image, 'infinity'])
        target['web_container'] = name
        try:
            yield
        finally:
            target['web_container'] = original
            ops.run(['docker', 'rm', '--force', name])

    def close(self):
        target = self.config['beta']
        source_id, beta_id = self.redis_identity('source'), self.redis_identity('beta')
        source_sql = self.mysql('source', 'SELECT @@server_uuid, DATABASE();').strip().split('\t')[0]
        beta_sql = self.mysql('beta', 'SELECT @@server_uuid, DATABASE();').strip().split('\t')[0]
        ops.require(source_id != beta_id and source_sql != beta_sql, 'production_runtime_alias')
        php = ('require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
               '$kernel=$app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); '
               '$app->make(App\\Support\\DeploymentReadiness::class)->assertBetaConfiguration(); '
               'if (!app()->environment("beta") || '
               'config("app.maintenance.driver")!=="cache" || config("app.maintenance.store")!=="redis" || '
               'config("cache.prefix")!=="dlp_friends_beta_cache_" || '
               'config("database.redis.options.prefix")!=="dlp_friends_beta_" || '
               'config("cache.stores.redis.connection")!=="cache" || '
               '(string)config("database.redis.default.database")!=="0" || (string)config("database.redis.cache.database")!=="1" || '
               'config("filesystems.disks.s3.bucket")!==' + json.dumps(target['bucket']) + ' || '
               'config("filesystems.disks.s3.key")!==' + json.dumps(target['storage_key_id']) + ' || '
               'config("database.connections.mysql.database")!==' + json.dumps(target['database']) + ' || '
               'Illuminate\\Support\\Facades\\DB::selectOne("SELECT @@server_uuid AS uuid")->uuid!==' + json.dumps(beta_sql) + ' || '
               'Illuminate\\Support\\Facades\\Redis::connection("cache")->info("server")["run_id"]!==' + json.dumps(beta_id) + ' || '
               'Illuminate\\Support\\Facades\\Redis::connection("default")->info("server")["run_id"]!==' + json.dumps(beta_id) + ') exit(78); '
               'if($kernel->call("down",["--retry"=>60])!==0 || !app()->isDownForMaintenance())exit(78);')
        ops.run(['docker', 'exec', target['web_container'], 'php', '-r', php])

    def close_for_stop(self, previous):
        target = self.config['beta']
        running = lambda name: self.exists(name) and self.inspect(name)['State']['Running']
        if running(target['mysql_container']) and running(target['redis_container']):
            if running(target['web_container']):
                self.close()
            else:
                image = self.inspect(target['web_container'])['Image'] if self.exists(target['web_container']) else previous.get('image')
                ops.require(image, 'maintenance_image_required')
                with self.temporary(image):
                    self.close()
        else:
            # An already stopped lifecycle needs no restart; unknown partial stops fail closed.
            ops.require(previous.get('status') == 'stopped' and not running(target['web_container'])
                        and not running(target['mysql_container']) and not running(target['redis_container']),
                        'maintenance_before_stop_required')

    def bootstrap(self, image):
        target = self.config['beta']
        credentials = {'email': target['admin_email'], 'password': secrets.token_urlsafe(48)}
        path = self.directory / 'beta-admin.json'
        ops.write_json(path, credentials)
        ops.private_file(path)
        ops.run(['docker', 'cp', str(path), target['web_container'] + ':/tmp/beta-admin.json'])
        try:
            self.artisan('beta', 'beta:bootstrap-admin', '--credentials=/tmp/beta-admin.json', '--force', '--no-interaction')
        finally:
            ops.run(['docker', 'exec', target['web_container'], 'rm', '-f', '/tmp/beta-admin.json'])

    def wait_deployment(self, deployment):
        deadline = time.monotonic() + 900
        while time.monotonic() < deadline:
            status = self.api('/deployments/' + deployment)['status']
            if status == 'finished':
                return
            ops.require(status in ('queued', 'in_progress'), 'beta_deployment_failed')
            time.sleep(3)
        raise ops.OperationError('beta_deployment_timeout')

    def verify_deployed(self, image):
        target = self.config['beta']
        # Temporary configuration points web_container at the isolated Artisan container.
        containers = [self.live_web, *target['process_containers']]
        self.wait_containers(containers, True)
        expected = json.loads(ops.run(['docker', 'image', 'inspect', image]))[0]['Id']
        for name in containers:
            ops.require(self.inspect(name)['Image'] == expected, 'deployed_image_mismatch')
        temporary = target['web_container']
        target['web_container'] = self.live_web
        try:
            self.close()
            self.artisan('beta', 'deployment:check', '--allow-maintenance', '--json')
        finally:
            target['web_container'] = temporary

    def open(self):
        self.artisan('beta', 'up', '--no-interaction')
        temporary = self.config['beta']['web_container']
        self.config['beta']['web_container'] = self.live_web
        try:
            self.artisan('beta', 'deployment:check', '--json')
        finally:
            self.config['beta']['web_container'] = temporary

    def execute(self, request):
        action = request['action']
        initialize = action.startswith('initialize-')
        ops.require(not initialize or request.get('replace_beta_tests') is True, 'explicit_beta_replacement_required')
        ops.require(action != 'update' or request.get('replace_beta_tests') is False, 'update_must_preserve_trials')
        path = self.directory / 'beta-lifecycle.json'
        with self.lock():
            previous = self.read('beta-lifecycle.json') if path.exists() else {}
            ops.require(action != 'update' or previous.get('initialized') is True, 'complete_beta_initialization_required')
            self.resources()
            state = {**previous, 'action': action, 'status': 'in_progress', 'started_at': int(time.time())}
            if initialize:
                state['initialized'] = False
            ops.write_json(path, state)
            deployment = None
            try:
                if action == 'stop':
                    self.close_for_stop(previous)
                    self.application_stop()
                    self.dependencies('stop')
                    state.update(status='stopped', completed_at=int(time.time()))
                    ops.write_json(path, state)
                    return {'status': 'stopped'}
                self.image(request['image'], request['sha'])
                self.application_stop()
                self.dependencies('start')
                self.live_web = self.config['beta']['web_container']
                with self.temporary(request['image']):
                    self.close()
                    try:
                        self.validate_targets()
                        # Neither an updated trial DB nor a catalog export attests a production rehearsal.
                        for name in ('rehearsal.json', 'snapshot.json', 'beta-journeys.json'):
                            (self.directory / name).unlink(missing_ok=True)
                        if initialize:
                            self._refresh('references' if action == 'initialize-references' else 'production')
                        self.beta_command(request['image'], 'migrate', '--force', '--no-interaction')
                        if action == 'initialize-references':
                            self.bootstrap(request['image'])
                        self.beta_command(request['image'], 'deployment:check', '--allow-maintenance', '--json')
                        deployment = self.queue(request)['deployment_uuid']
                        state.update(deployment_uuid=deployment, phase='deploying')
                        ops.write_json(path, state)
                        self.wait_deployment(deployment)
                        self.verify_deployed(request['image'])
                        self.beta_command(request['image'], 'deployment:check', '--allow-maintenance', '--json')
                        self.open()
                        state.update(status='ready', initialized=True, image=request['image'], sha=request['sha'], completed_at=int(time.time()))
                        ops.write_json(path, state)
                    except BaseException:
                        self.close()
                        raise
                return {'status': 'ready', 'deployment_uuid': deployment}
            except BaseException:
                state.update(status='failed', completed_at=int(time.time()))
                ops.write_json(path, state)
                try:
                    if deployment and self.api('/deployments/' + deployment).get('status') in ('queued', 'in_progress'):
                        self.api('/deployments/' + deployment + '/cancel', 'POST')
                finally:
                    self.application_stop()
                raise
