#!/usr/bin/env python3
"""Private server operations. Never print subprocess output or API error bodies."""
import argparse
import contextlib
import configparser
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import time
import uuid


class OperationError(Exception):
    pass


def require(condition, reason):
    if not condition:
        raise OperationError(reason)


def private_file(path):
    path = Path(path)
    info = path.lstat()
    require(stat.S_ISREG(info.st_mode) and info.st_uid == os.geteuid()
            and stat.S_IMODE(info.st_mode) == 0o600, "private_file_required")
    return path


def run(arguments, *, input=None, output=None):
    result = subprocess.run(arguments, input=input, stdout=output or subprocess.PIPE,
                            stderr=subprocess.PIPE, check=False)
    require(result.returncode == 0, "external_operation_failed")
    return result.stdout.decode() if output is None else ""


def digest(value):
    return hashlib.sha256(value.encode()).hexdigest()


def normalize_schema(definitions):
    normalized = []
    for definition in definitions:
        # Only a table's allocation counter is volatile; retain column AUTO_INCREMENT.
        definition = re.sub(r"(\) ENGINE=[^\n]*?) AUTO_INCREMENT=\d+", r"\1", definition)
        # COLLATE already determines its charset. Server versions can spell out
        # this redundant attribute differently. Never rewrite SQL string literals
        # or identifiers, nor discard a distinct charset or collation.
        parts = re.split(r"('(?:\\.|''|[^'\\])*'|`(?:``|[^`])*`)", definition)
        for index in range(0, len(parts), 2):
            parts[index] = re.sub(r" CHARACTER SET ([A-Za-z0-9]+) (?=COLLATE \1_[A-Za-z0-9_]+\b)", " ", parts[index])
        normalized.append("".join(parts))
    return "\n".join(sorted(normalized))


def write_json(path, value):
    temporary = Path(str(path) + ".tmp")
    with temporary.open("w") as file:
        json.dump(value, file, sort_keys=True)
        file.flush()
        os.fsync(file.fileno())
    temporary.replace(path)


# Only application-managed catalogs/settings; new tables are excluded by default.
REFERENCE_TABLES = ("roles", "avatars", "interest_categories", "interests", "interest_settings",
                    "conversation_starters", "partner_settings", "product_onboarding_settings", "seasonal_themes")


class Operations:
    def __init__(self, config_path):
        self.config_path = private_file(config_path)
        self.config = json.loads(self.config_path.read_text())
        self.directory = Path(self.config["report_dir"])
        require(self.directory.is_absolute() and self.directory.is_dir(), "private_report_directory_required")
        require(self.directory.stat().st_uid == os.geteuid()
                and stat.S_IMODE(self.directory.stat().st_mode) == 0o700, "private_report_directory_required")
        for side in ("source", "beta"):
            target = self.config[side]
            require(re.fullmatch(r"[A-Za-z0-9_]+", target["database"]), "invalid_database")
            private_file(target["mysql_client"])
        private_file(self.config["rclone_config"])
        private_file(self.config["garage_curl_config"])

    def mysql(self, side, sql, *, input=None):
        target = self.config[side]
        arguments = ["mysql", "--defaults-extra-file=" + target["mysql_client"],
                     "--default-character-set=utf8mb4", "--batch", "--skip-column-names", "--raw", target["database"]]
        return run(arguments + (["--execute", sql] if input is None else []), input=input)

    def inspect(self, container):
        result = json.loads(run(["docker", "inspect", container]))
        require(len(result) == 1, "ambiguous_container")
        return result[0]

    def artisan(self, side, *arguments):
        return run(["docker", "exec", self.config[side]["web_container"], "php", "artisan", *arguments])

    def garage(self, operation, query):
        from urllib.parse import urlencode
        endpoint = self.config["garage_admin_url"].rstrip("/")
        require(endpoint.startswith("https://") or endpoint.startswith("http://127.0.0.1:"), "private_api_transport_required")
        return json.loads(run(["curl", "--config", self.config["garage_curl_config"],
                               "--silent", "--fail", "--connect-timeout", "10", "--max-time", "60",
                               endpoint + "/v2/" + operation + "?" + urlencode(query)]))

    def redis_identity(self, side):
        password = private_file(self.config[side]["redis_password_file"]).read_bytes()
        require(b"\n" not in password.rstrip(b"\n") and password.strip(), "redis_password_file_required")
        result = run(["docker", "exec", "-i", self.config[side]["redis_container"], "sh", "-c",
                      'IFS= read -r REDISCLI_AUTH; export REDISCLI_AUTH; exec redis-cli --raw INFO server'],
                     input=password.rstrip(b"\n") + b"\n")
        match = re.search(r"^run_id:([A-Za-z0-9]+)\r?$", result, re.M)
        require(match is not None, "redis_identity_unavailable")
        return match[1]

    def reset_beta_runtime(self):
        identity = self.redis_identity("beta")
        lua = ('if redis.call("EXISTS",ARGV[1]) ~= 1 then return 0 end; '
               'for _,key in ipairs(redis.call("KEYS","*")) do if key ~= ARGV[1] then redis.call("DEL",key) end end; return 1')
        # Only the dedicated beta server may be cleared. Retain maintenance atomically.
        php = ('require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
               '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
               '$default=Illuminate\\Support\\Facades\\Redis::connection("default"); '
               '$cache=Illuminate\\Support\\Facades\\Redis::connection("cache"); '
               'if (!app()->environment("beta") || !app()->isDownForMaintenance() || '
               'config("app.maintenance.driver")!=="cache" || config("app.maintenance.store")!=="redis" || '
               'config("cache.prefix")!=="dlp_friends_beta_cache_" || '
               '(string)config("database.redis.default.database")!=="0" || (string)config("database.redis.cache.database")!=="1" || '
               '$default->info("server")["run_id"]!==' + json.dumps(identity) + ' || '
               '$cache->info("server")["run_id"]!==' + json.dumps(identity) + ') exit(78); '
               '$key=$cache->client()->getOption(Redis::OPT_PREFIX).config("cache.prefix")."illuminate:foundation:down"; '
               '$lua=' + json.dumps(lua) + '; '
               'if ($cache->eval($lua,0,$key)!==1) exit(78); $default->flushdb(); '
               'if (!app()->isDownForMaintenance()) exit(78);')
        run(["docker", "exec", self.config["beta"]["web_container"], "php", "-r", php])

    def validate_targets(self):
        source, beta = self.config["source"], self.config["beta"]
        for key in ("application_uuid", "mysql_uuid", "redis_uuid"):
            require(source[key] and beta[key] and source[key] != beta[key], "resource_alias")
        identities = [self.mysql(side, "SELECT @@server_uuid, DATABASE();").strip() for side in ("source", "beta")]
        require(all("\t" in identity for identity in identities) and identities[0] != identities[1], "mysql_alias")
        # A separate schema on production's MySQL instance is deliberately insufficient.
        require(identities[0].split("\t")[0] != identities[1].split("\t")[0], "mysql_server_shared")
        containers = {side: [self.inspect(self.config[side][key])
                              for key in ("mysql_container", "redis_container")] for side in ("source", "beta")}
        ids = {side: {item["Id"] for item in items} for side, items in containers.items()}
        require(len(ids["source"]) == 2 and len(ids["beta"]) == 2 and ids["source"].isdisjoint(ids["beta"]), "container_alias")
        mounts = {side: {mount["Source"] for item in items for mount in item["Mounts"]
                         if mount["Destination"] in ("/var/lib/mysql", "/data")} for side, items in containers.items()}
        require(len(mounts["source"]) == 2 and len(mounts["beta"]) == 2, "persistent_volumes_required")
        require(not any(a == b or a.startswith(b.rstrip("/") + "/") or b.startswith(a.rstrip("/") + "/")
                        for a in mounts["source"] for b in mounts["beta"]), "volume_alias")
        grants = self.mysql("beta", "SHOW GRANTS;")
        for line in grants.splitlines():
            require(" ON *.* " not in line or line.startswith("GRANT USAGE ON *.* "), "beta_global_privileges")
            if not line.startswith("GRANT USAGE ON *.* "):
                # Database-level grants use LIKE patterns: an underscore must be literal.
                database_pattern = beta["database"].replace("_", "\\_")
                require(" ON `" + database_pattern + "`.* TO " in line and "WITH GRANT OPTION" not in line,
                        "beta_database_privileges")
        require(bool(grants.strip()), "beta_database_privileges")
        buckets = {side: self.garage("GetBucketInfo", {"globalAlias": self.config[side]["bucket"]})
                   for side in ("source", "beta")}
        require(buckets["source"]["id"] != buckets["beta"]["id"], "bucket_alias")
        require(all(buckets[side]["id"] == self.config[side]["bucket_id"] for side in buckets), "bucket_identity_changed")
        require(buckets["beta"].get("websiteAccess") is False, "private_bucket_required")
        key = self.garage("GetKeyInfo", {"id": beta["storage_key_id"], "showSecretKey": "false"})
        require(key["accessKeyId"] == beta["storage_key_id"] and not key["expired"], "invalid_beta_storage_key")
        require(not key["permissions"].get("createBucket", True), "beta_storage_admin")
        allowed = key["buckets"]
        require(len(allowed) == 1 and allowed[0]["id"] == buckets["beta"]["id"]
                and allowed[0]["permissions"].get("read") is True
                and allowed[0]["permissions"].get("write") is True
                and allowed[0]["permissions"].get("owner", True) is False, "beta_storage_permissions")
        remotes = configparser.ConfigParser(interpolation=None)
        remotes.read(self.config["rclone_config"])
        for side in ("source", "beta"):
            remote = remotes[self.config[side]["rclone_remote"]]
            require(remote["type"] == "s3" and remote["endpoint"].rstrip("/") == self.config["garage_s3_endpoint"].rstrip("/")
                    and remote["access_key_id"] == self.config[side]["storage_key_id"], "rclone_identity_mismatch")
        redis = {side: self.redis_identity(side) for side in ("source", "beta")}
        require(redis["source"] != redis["beta"], "redis_server_shared")
        # Check the actual running web configuration, not only operator declarations.
        php = ('require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
               '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
               'echo json_encode(["env"=>app()->environment(),"closed"=>app()->isDownForMaintenance(),'
               '"database"=>config("database.connections.mysql.database"),'
               '"bucket"=>config("filesystems.disks.s3.bucket"),"key"=>config("filesystems.disks.s3.key"),'
               '"redis_default_id"=>Illuminate\\Support\\Facades\\Redis::connection("default")->info("server")["run_id"],'
               '"redis_cache_id"=>Illuminate\\Support\\Facades\\Redis::connection("cache")->info("server")["run_id"],'
               '"mysql_server_uuid"=>Illuminate\\Support\\Facades\\DB::selectOne("SELECT @@server_uuid AS uuid")->uuid]);')
        runtime = json.loads(run(["docker", "exec", beta["web_container"], "php", "-r", php]))
        require(runtime == {"env": "beta", "closed": True, "database": beta["database"],
                            "bucket": beta["bucket"], "key": beta["storage_key_id"],
                            "redis_default_id": redis["beta"], "redis_cache_id": redis["beta"],
                            "mysql_server_uuid": identities[1].split("\t")[0]}, "beta_closed_identity_required")
        require(len(beta["process_containers"]) == 3, "all_processes_required")
        for container in beta["process_containers"]:
            if hasattr(self, "exists") and not self.exists(container):
                continue
            state = self.inspect(container)["State"]
            require(state["Running"] is False and state["ExitCode"] == 0, "beta_processes_not_drained")
        side_effects = self.mysql("source", "SELECT (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()) + (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()) + (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE());")
        require(side_effects.strip() == "0", "database_side_effects_require_review")
        engines = self.mysql("source", "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (ENGINE IS NULL OR ENGINE <> 'InnoDB');")
        require(engines.strip() == "0", "transactional_tables_required")

    def tables(self, side):
        tables = self.mysql(side, "SHOW TABLES;").splitlines()
        require(all(re.fullmatch(r"[A-Za-z0-9_]+", table) for table in tables), "unsupported_table_name")
        return sorted(tables)

    def schema_fingerprint(self, side):
        definitions = []
        for table in self.tables(side):
            definition = self.mysql(side, "SHOW CREATE TABLE `" + table + "`;")
            require(definition.startswith(table + "\tCREATE TABLE "), "invalid_schema_definition")
            definitions.append(definition.split("\t", 1)[1].strip())
        return digest(normalize_schema(definitions))

    def inventory(self, side):
        target = self.config[side]
        result = json.loads(run(["rclone", "--config", self.config["rclone_config"], "lsjson", "--recursive", "--files-only", "--hash",
                                 target["rclone_remote"] + ":" + target["bucket"]]))
        return sorted([{"path": item["Path"], "size": item["Size"], "hashes": item["Hashes"]} for item in result], key=lambda item: item["path"])

    @contextlib.contextmanager
    def lock(self):
        with (self.directory / "operation.lock").open("a") as file:
            fcntl.flock(file, fcntl.LOCK_EX | fcntl.LOCK_NB)
            yield

    def dump(self, path, mode):
        require(mode in ("production", "references"), "invalid_data_mode")
        source = self.config["source"]
        arguments = ["mysqldump", "--defaults-extra-file=" + source["mysql_client"], "--single-transaction", "--quick",
                     "--hex-blob", "--no-tablespaces", "--set-gtid-purged=OFF", "--default-character-set=utf8mb4",
                     "--skip-triggers", "--skip-extended-insert", "--complete-insert", "--skip-comments"]
        with path.open("wb") as output:
            if mode == "production":
                run(arguments + [source["database"]], output=output)
            else:
                require(set(REFERENCE_TABLES).issubset(self.tables("source")), "reference_schema_missing")
                run(arguments + ["--no-data", source["database"]], output=output)
                run(arguments + ["--no-create-info", source["database"], "migrations", *REFERENCE_TABLES], output=output)

    def reference_foreign_keys(self, side, verify=False):
        rows = self.mysql(side, "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;")
        for row in rows.splitlines():
            table, column, parent, parent_column = row.split("\t")
            if table not in REFERENCE_TABLES:
                continue
            require(parent in REFERENCE_TABLES and all(re.fullmatch(r"[A-Za-z0-9_]+", value)
                    for value in (table, column, parent, parent_column)), "reference_dependency_requires_review")
            if verify:
                sql = (f"SELECT COUNT(*) FROM `{table}` AS child LEFT JOIN `{parent}` AS parent "
                       f"ON child.`{column}`=parent.`{parent_column}` WHERE child.`{column}` IS NOT NULL AND parent.`{parent_column}` IS NULL;")
                require(self.mysql(side, sql).strip() == "0", "reference_foreign_key_missing")

    def reference_objects(self):
        paths = self.mysql("beta", "SELECT image_path FROM `avatars` ORDER BY image_path;").splitlines()
        require(all(path.startswith("avatars/") and not any(part in ("", ".", "..") for part in path.split("/"))
                    and not any(character in path for character in "\r\n\\") for path in paths), "invalid_reference_object")
        return sorted(set(paths))

    def refresh(self, replace, mode="production"):
        require(replace, "explicit_beta_replacement_required")
        require(mode in ("production", "references"), "invalid_data_mode")
        with self.lock():
            lifecycle = self.directory / "beta-lifecycle.json"
            if lifecycle.exists():
                state = json.loads(private_file(lifecycle).read_text())
                write_json(lifecycle, {**state, "initialized": False, "status": "requires_initialization"})
            self._refresh(mode)

    def _refresh(self, mode):
        snapshot_file = self.directory / "snapshot.json"
        snapshot_file.unlink(missing_ok=True)
        self.validate_targets()
        created_at = int(time.time())
        source_schema = self.schema_fingerprint("source")
        applied = self.mysql("source", "SELECT migration, batch FROM migrations ORDER BY migration;")
        before = self.inventory("source")
        snapshot_id = uuid.uuid4().hex
        dump = self.directory / (snapshot_id + ".sql")
        if mode == "references":
            self.reference_foreign_keys("source")
        self.dump(dump, mode)
        require(self.schema_fingerprint("source") == source_schema, "source_schema_changed")
        # Each physical INSERT line is exactly one row from the same dump transaction.
        counts = {}
        with dump.open() as file:
            for line in file:
                created = re.match(r"CREATE TABLE `([A-Za-z0-9_]+)`", line)
                inserted = re.match(r"INSERT INTO `([A-Za-z0-9_]+)`", line)
                if created:
                    counts[created[1]] = 0
                if inserted:
                    require(inserted[1] in counts, "invalid_dump")
                    counts[inserted[1]] += 1
        require(bool(counts) and "migrations" in counts, "incomplete_dump")
        # Recheck every identity immediately before the first destructive operation.
        self.validate_targets()
        tables = self.tables("beta")
        if tables:
            self.mysql("beta", "SET FOREIGN_KEY_CHECKS=0; " + "; ".join("DROP TABLE `" + table + "`" for table in tables) + "; SET FOREIGN_KEY_CHECKS=1;")
        with dump.open("rb") as file:
            self.mysql("beta", "", input=file.read())
        require(self.schema_fingerprint("beta") == source_schema and self.tables("beta") == sorted(counts), "restored_schema_mismatch")
        for table, count in counts.items():
            require(self.mysql("beta", "SELECT COUNT(*) FROM `" + table + "`;").strip() == str(count), "restored_count_mismatch")
        require(self.mysql("beta", "SELECT migration, batch FROM migrations ORDER BY migration;") == applied, "restored_migrations_mismatch")
        if mode == "references":
            self.reference_foreign_keys("beta", verify=True)
        source, beta = self.config["source"], self.config["beta"]
        if mode == "production":
            run(["rclone", "--config", self.config["rclone_config"], "sync", source["rclone_remote"] + ":" + source["bucket"],
                 beta["rclone_remote"] + ":" + beta["bucket"]])
            run(["rclone", "--config", self.config["rclone_config"], "check", "--download", source["rclone_remote"] + ":" + source["bucket"],
                 beta["rclone_remote"] + ":" + beta["bucket"]])
            require(self.inventory("source") == before and self.inventory("beta") == before, "objects_changed_during_copy")
        else:
            # Read paths from the restored transaction, never from a mutable source query.
            paths = self.reference_objects()
            selected = [item for item in before if item["path"] in paths]
            require(len(selected) == len(paths), "reference_object_missing")
            selection = self.directory / "reference-objects.txt"
            selection.write_text("".join(path + "\n" for path in paths))
            run(["rclone", "--config", self.config["rclone_config"], "delete", beta["rclone_remote"] + ":" + beta["bucket"]])
            if paths:
                run(["rclone", "--config", self.config["rclone_config"], "copy", "--files-from-raw", str(selection),
                     source["rclone_remote"] + ":" + source["bucket"], beta["rclone_remote"] + ":" + beta["bucket"]])
                run(["rclone", "--config", self.config["rclone_config"], "check", "--download", "--files-from-raw", str(selection),
                     source["rclone_remote"] + ":" + source["bucket"], beta["rclone_remote"] + ":" + beta["bucket"]])
            require(self.inventory("beta") == selected
                    and [item for item in self.inventory("source") if item["path"] in paths] == selected,
                    "objects_changed_during_copy")
        self.reset_beta_runtime()
        self.artisan("beta", "beta:sanitize-copy", "--force", "--no-interaction")
        write_json(snapshot_file, {"snapshot_id": snapshot_id, "data_mode": mode, "created_at": created_at,
                                  "source_schema_sha256": source_schema, "source_migrations_sha256": digest(applied),
                                  "tables_verified": True, "objects_verified": True, "sanitized": True})


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument("operation", choices=["refresh", "validate-targets", "schema-fingerprint"])
    parser.add_argument("--config", required=True)
    parser.add_argument("--replace-beta-tests", action="store_true")
    parser.add_argument("--data-mode", choices=["production", "references"], default="production")
    parser.add_argument("--side", choices=["source", "beta"], default="source")
    args = parser.parse_args()
    operations = Operations(args.config)
    if args.operation == "refresh":
        operations.refresh(args.replace_beta_tests, args.data_mode)
        print("snapshot_verified_beta_remains_closed")
    elif args.operation == "validate-targets":
        operations.validate_targets()
    else:
        print(operations.schema_fingerprint(args.side))


if __name__ == "__main__":
    try:
        main()
    except (Exception, KeyboardInterrupt):
        print("operation_failed_beta_remains_closed", file=sys.stderr)
        sys.exit(1)
