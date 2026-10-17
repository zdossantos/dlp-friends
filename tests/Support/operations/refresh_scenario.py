"""Synthetic boundary fixtures for the server-only refresh command."""
import importlib.util
import sys
import json
import tempfile
from types import SimpleNamespace

from pathlib import Path

module = Path(__file__).resolve().parents[3] / "scripts/operations/operations.py"
spec = importlib.util.spec_from_file_location("operations", module)
ops = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ops)

scenario = sys.argv[1]
if scenario == "private-error":
    ops.subprocess.run = lambda *args, **kwargs: SimpleNamespace(returncode=1, stdout=b"private-response", stderr=b"private-response")
    try:
        ops.run(["curl", "unused"])
        raise AssertionError("private API failure accepted")
    except ops.OperationError as error:
        assert "private-response" not in str(error)
    sys.exit(0)

if scenario == "schema-fingerprint":
    a = "CREATE TABLE `users` (\n `id` bigint NOT NULL AUTO_INCREMENT,\n PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4"
    b = a.replace("AUTO_INCREMENT=12", "AUTO_INCREMENT=400")
    assert ops.normalize_schema([a]) == ops.normalize_schema([b])
    assert ops.normalize_schema([a]) == a.replace(" AUTO_INCREMENT=12", "")
    assert ops.normalize_schema([a]) != ops.normalize_schema([a.replace("`id` bigint", "`id` int")])
    sys.exit(0)

with tempfile.TemporaryDirectory() as temporary:
    directory = Path(temporary)
    directory.chmod(0o700)
    private = directory / "client"
    private.write_text("[source]\ntype=s3\nendpoint=https://s3.example.test\naccess_key_id=source-key\n[beta]\ntype=s3\nendpoint=https://s3.example.test\naccess_key_id=" + ("source-key" if scenario == "rclone-key-alias" else "beta-key") + "\n")
    private.chmod(0o600)
    config = {"report_dir": temporary, "rclone_config": str(private), "garage_curl_config": str(private),
              "garage_admin_url": "https://garage.example.test", "garage_s3_endpoint": "https://s3.example.test"}
    for side in ("source", "beta"):
        config[side] = {"application_uuid": side + "-app", "mysql_uuid": side + "-mysql", "redis_uuid": side + "-redis",
                        "mysql_container": side + "-mysql", "redis_container": side + "-redis", "web_container": side + "-web",
                        "process_containers": [side + "-worker", side + "-scheduler", side + "-reverb"],
                        "database": side, "mysql_client": str(private), "bucket": side, "bucket_id": side + "-bucket",
                        "storage_key_id": side + "-key", "rclone_remote": side}
    if scenario == "resource-alias":
        config["beta"]["redis_uuid"] = config["source"]["redis_uuid"]
    path = directory / "config.json"
    path.write_text(json.dumps(config))
    path.chmod(0o600)
    calls = []
    imported = False
    copied = False
    mutations = []

    def fake(arguments, *, input=None, output=None):
        global imported, copied
        calls.append(arguments)
        if arguments[0] == "mysql":
            side = arguments[5]
            sql = arguments[7] if input is None else "IMPORT"
            if "server_uuid" in sql:
                return ("source\tsource" if scenario == "mysql-alias" or side == "source" else "beta\tbeta") + "\n"
            if sql == "SHOW GRANTS;":
                return "GRANT USAGE ON *.* TO 'beta'@'%'\nGRANT ALL PRIVILEGES ON `beta`.* TO 'beta'@'%'\n"
            if "information_schema" in sql:
                return "0\n"
            if sql == "SHOW TABLES;":
                return "migrations\nusers\n" if side == "source" or imported else "old_beta_tests\n"
            if sql.startswith("SHOW CREATE"):
                table = sql.split("`")[1]
                return table + "\tCREATE TABLE `" + table + "` (\n `id` bigint NOT NULL\n) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4\n"
            if sql.startswith("SELECT migration"):
                return "create_users\t1\n"
            if sql.startswith("SELECT COUNT"):
                return "0\n" if scenario == "incomplete-import" else "1\n"
            if sql.startswith("SET FOREIGN_KEY_CHECKS"):
                mutations.append("drop-beta")
                return ""
            if sql == "IMPORT":
                mutations.append("import-beta")
                if scenario == "interrupted-copy":
                    raise KeyboardInterrupt()
                imported = True
                return ""
            raise AssertionError(sql)
        if arguments[0] == "docker":
            if arguments[1] == "inspect":
                name = arguments[2]
                volume = "/volumes/" + name
                if scenario == "volume-alias" and name == "beta-mysql":
                    volume = "/volumes/source-mysql"
                destination = "/var/lib/mysql" if name.endswith("mysql") else "/data"
                return json.dumps([{"Id": name, "Mounts": [{"Source": volume, "Destination": destination}],
                                    "State": {"Running": scenario == "active-process", "ExitCode": 0}}])
            if "-r" in arguments:
                return json.dumps({"env": "beta", "closed": True, "database": "beta", "bucket": "beta", "key": "beta-key",
                                   "mysql_server_uuid": "source" if scenario == "runtime-mysql-alias" else "beta"})
            assert "beta:sanitize-copy" in arguments
            mutations.append("sanitize-beta")
            return "sanitized\n"
        if arguments[0] == "curl":
            url = arguments[-1]
            if "GetBucketInfo" in url:
                side = "source" if url.endswith("=source") else "beta"
                return json.dumps({"id": "source-bucket" if scenario == "bucket-alias" else side + "-bucket", "websiteAccess": False})
            buckets = [{"id": "beta-bucket", "permissions": {"read": True, "write": True, "owner": False}}]
            if scenario == "source-bucket-write":
                buckets.append({"id": "source-bucket", "permissions": {"write": True}})
            return json.dumps({"accessKeyId": "beta-key", "expired": False, "permissions": {"createBucket": False}, "buckets": buckets})
        if arguments[0] == "mysqldump":
            output.write(b"CREATE TABLE `migrations` (\n `id` bigint\n);\nINSERT INTO `migrations` (`id`) VALUES (1);\nCREATE TABLE `users` (\n `id` bigint\n);\nINSERT INTO `users` (`id`) VALUES (1);\n")
            return ""
        if arguments[0] == "rclone":
            if "lsjson" in arguments:
                return "[]" if copied and scenario == "object-disappeared" else json.dumps([{"Path": "synthetic.png", "Size": 123, "Hashes": {"md5": "synthetic"}}])
            if "sync" in arguments:
                mutations.append("copy-beta-bucket")
                copied = True
            return ""
        raise AssertionError(arguments)

    ops.run = fake
    operation = ops.Operations(path)
    expected = {"mysql-alias": "mysql_alias", "resource-alias": "resource_alias", "volume-alias": "volume_alias",
                "bucket-alias": "bucket_alias", "source-bucket-write": "beta_storage_permissions",
                "missing-confirmation": "explicit_beta_replacement_required", "active-process": "beta_processes_not_drained",
                "incomplete-import": "restored_count_mismatch", "object-disappeared": "objects_changed_during_copy",
                "runtime-mysql-alias": "beta_closed_identity_required", "rclone-key-alias": "rclone_identity_mismatch"}
    try:
        operation.refresh(scenario != "missing-confirmation")
        assert scenario == "complete-copy", "unsafe refresh accepted"
        report = json.loads((directory / "snapshot.json").read_text())
        assert report["tables_verified"] and report["objects_verified"] and report["sanitized"]
        assert mutations == ["drop-beta", "import-beta", "copy-beta-bucket", "sanitize-beta"]
    except ops.OperationError as error:
        assert str(error) == expected[scenario], str(error)
        assert not (directory / "snapshot.json").exists()
    except KeyboardInterrupt:
        assert scenario == "interrupted-copy"
        assert not (directory / "snapshot.json").exists()
    if scenario in list(expected)[:7] or scenario in ("runtime-mysql-alias", "rclone-key-alias"):
        assert not mutations, mutations
    assert not any("up" in call or "migrate" in call for call in calls)
