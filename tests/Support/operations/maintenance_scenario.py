"""Execute the real PHP maintenance guard with synthetic Laravel boundaries."""
import json
from pathlib import Path
import subprocess
import sys
import tempfile
sys.path.insert(0, str(Path(__file__).resolve().parents[3] / 'scripts/operations'))
import beta_lifecycle as beta
import operations as ops
scenario = sys.argv[1]
operation = object.__new__(beta.BetaLifecycle)
operation.config = {'beta': {'web_container': 'temporary', 'bucket': 'beta', 'storage_key_id': 'beta-key', 'database': 'beta'}}
operation.redis_identity = lambda side: side
operation.mysql = lambda side, sql: side+'\tbeta'
values = {'app.maintenance.driver':'cache','app.maintenance.store':'redis','cache.prefix':'dlp_friends_beta_cache_', 'cache.stores.redis.connection':'cache', 'database.redis.options.prefix':'dlp_friends_beta_', 'database.redis.default.database':'0','database.redis.cache.database':'1','filesystems.disks.s3.bucket':'beta','filesystems.disks.s3.key':'beta-key','database.connections.mysql.database':'beta'}
if scenario == 'wrong-prefix':
    values['database.redis.options.prefix'] = 'production_'
if scenario == 'wrong-connection':
    values['cache.stores.redis.connection'] = 'default'
with tempfile.TemporaryDirectory() as directory:
    path = Path(directory)
    (path/'vendor').mkdir()
    (path/'vendor/autoload.php').write_text('<?php')
    (path/'bootstrap').mkdir()
    (path/'bootstrap/app.php').write_text('''<?php
class Kernel {function bootstrap(){} function call($command,$args){echo 'closed';return 0;}}
class Readiness {function assertBetaConfiguration(){}}
class Application {function make($name){return str_contains($name,'DeploymentReadiness')?new Readiness:new Kernel;}function environment($name){return $name==='beta';}function isDownForMaintenance(){return CLOSED;}}
function app(){return new Application;}
function config($key){return json_decode(base64_decode('VALUES'),true)[$key]??null;}
class FakeDB {static function selectOne($sql){return (object)['uuid'=>'beta'];}}
class FakeRedis {static function connection($name){return new self;}function info($name){return ['run_id'=>'beta'];}}
class_alias(FakeDB::class,'Illuminate\\Support\\Facades\\DB');
class_alias(FakeRedis::class,'Illuminate\\Support\\Facades\\Redis');
return new Application;
'''.replace('CLOSED', 'false' if scenario == 'down-not-persisted' else 'true').replace('VALUES', __import__('base64').b64encode(json.dumps(values).encode()).decode()))
    def run(arguments, **kwargs):
        result = subprocess.run(['php','-r',arguments[-1]], cwd=path, text=True,capture_output=True)
        if scenario in ('valid', 'reopening-failure'):
            assert result.returncode == 0 and result.stdout == 'closed', result.stderr
        elif scenario == 'down-not-persisted':
            assert result.returncode == 78 and result.stdout == 'closed', (result.returncode,result.stdout)
        else:
            assert result.returncode == 78 and result.stdout == '', (result.returncode,result.stdout,result.stderr)
        return result.stdout
    ops.run = run
    operation.close()
# Opening must verify the live container's readiness after removing maintenance.
events=[]
operation.live_web='live'
def artisan(side, *args):
    events.append((operation.config[side]['web_container'],args))
    if scenario == 'reopening-failure' and args[0] == 'deployment:check':
        raise ops.OperationError('still_closed')
operation.artisan=artisan
try:
    operation.open()
    assert scenario != 'reopening-failure'
except ops.OperationError:
    assert scenario == 'reopening-failure'
assert events == [('temporary',('up','--no-interaction')),('live',('deployment:check','--json'))], events
assert operation.config['beta']['web_container']=='temporary'
print('maintenance_verified')
