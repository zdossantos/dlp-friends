"""Reference export exercises the real SQL/object filtering without member data."""
import importlib.util
import json
from pathlib import Path
import tempfile
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[3] / 'scripts/operations'))
import operations as ops

with tempfile.TemporaryDirectory() as directory:
    operation = object.__new__(ops.Operations)
    operation.directory = Path(directory)
    operation.config = {'rclone_config': '/private/rclone', 'source': {'mysql_client': '/private/source', 'database': 'production', 'rclone_remote': 'source', 'bucket': 'prod'}, 'beta': {'rclone_remote': 'beta', 'bucket': 'beta'}}
    calls = []
    tables = sorted([*ops.REFERENCE_TABLES, 'migrations', 'users', 'messages', 'profiles', 'future_member_data'])
    operation.tables = lambda side: tables
    operation.mysql = lambda side, sql: 'avatars/catalog.png\n'
    def run(arguments, **kwargs):
        calls.append(arguments)
        if arguments[0] == 'mysqldump':
            if '--no-data' in arguments:
                kwargs['output'].write(''.join('CREATE TABLE `' + table + '` ();\n' for table in tables).encode())
            else:
                assert '--no-create-info' in arguments
                assert set(arguments[arguments.index('production')+1:]) == set(ops.REFERENCE_TABLES) | {'migrations'}
                assert not any(table in arguments for table in ['users', 'profiles', 'messages', 'future_member_data'])
                kwargs['output'].write(b'INSERT INTO `avatars` (`id`) VALUES (1);\n')
        return ''
    ops.run = run
    dump = Path(directory) / 'reference.sql'
    operation.dump(dump, 'references')
    assert 'INSERT INTO `users`' not in dump.read_text()
    assert operation.reference_objects() == ['avatars/catalog.png']
    operation.mysql = lambda side, sql: '../member/private.png\n'
    try:
        operation.reference_objects()
        raise AssertionError('unsafe object path accepted')
    except ops.OperationError:
        pass
print('reference_export_verified')

# Exercise the complete reference restore: excluded rows stay empty and private objects never copy.
import contextlib
import os
os.umask(0o077)
for empty in (False, True):
    with tempfile.TemporaryDirectory() as directory:
        operation.directory = Path(directory)
        operation.config['report_dir'] = directory
        operation.validate_targets = lambda: None
        operation.schema_fingerprint = lambda side: 'same-schema'
        operation.reset_beta_runtime = lambda: None
        operation.artisan = lambda *args: None
        operation.reference_foreign_keys = lambda *args, **kwargs: None
        imported = False
        copied = False
        objects = [{'path':'avatars/catalog.png', 'size':1}, {'path':'members/private.jpg', 'size':2}]
        def inventory(side):
            return ([] if empty else objects[:1]) if side == 'beta' else objects
        operation.inventory = inventory
        operation.tables = lambda side: tables if side == 'source' or imported else ['old_trials']
        def mysql(side, sql, input=None):
            global imported
            if input is not None:
                imported = True
                assert b'INSERT INTO `users`' not in input
                return ''
            if sql.startswith('SET FOREIGN'):
                assert side == 'beta'
                return ''
            if sql.startswith('SELECT migration'):
                return 'migration\t1\n'
            if sql.startswith('SELECT image_path'):
                return '' if empty else 'avatars/catalog.png\n'
            if sql.startswith('SELECT COUNT'):
                return '1' if sql == 'SELECT COUNT(*) FROM `avatars`;' else '0'
            raise AssertionError(sql)
        operation.mysql = mysql
        def dump(path, mode):
            assert mode == 'references'
            path.write_text(''.join('CREATE TABLE `' + table + '` ();\n' for table in tables)+'INSERT INTO `avatars` (`id`) VALUES (1);\n')
        operation.dump = dump
        calls = []
        def copy(arguments, **kwargs):
            calls.append(arguments)
            assert arguments[0] == 'rclone'
            if 'delete' in arguments:
                assert arguments[-1] == 'beta:beta'
            elif 'copy' in arguments or 'check' in arguments:
                assert not empty
                selection = Path(arguments[arguments.index('--files-from-raw')+1]).read_text()
                assert selection == 'avatars/catalog.png\n'
            else:
                raise AssertionError(arguments)
            return ''
        ops.run = copy
        operation._refresh('references')
        report = json.loads((Path(directory)/'snapshot.json').read_text())
        assert imported and report['data_mode'] == 'references' and report['objects_verified']
        assert len(calls) == (1 if empty else 3)

from release import Release
release = object.__new__(Release)
release.read = lambda name: {'data_mode': 'references'}
try:
    release.rehearse('image', 'commit')
    raise AssertionError('reference data used as production rehearsal')
except ops.OperationError as error:
    assert str(error) == 'full_production_snapshot_required'
print('reference_restore_verified')
