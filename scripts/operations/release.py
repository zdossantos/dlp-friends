#!/usr/bin/env python3
"""Operator-only releases. No automatic production migration or rollback."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import sys
import time
import operations as ops

JOURNEYS = ("login", "existing_profile", "discovery", "realtime_message", "file", "captured_mail", "queue")


def file_hash(file):
    result = hashlib.sha256()
    for chunk in iter(lambda: file.read(1024 * 1024), b""):
        result.update(chunk)
    return result.hexdigest()


class Release(ops.Operations):
    def read(self, name):
        path = self.directory / name
        ops.require(path.exists(), "private_report_missing")
        return json.loads(ops.private_file(path).read_text())

    def image(self, image, commit):
        ops.require(re.fullmatch(r"ghcr\.io/zdossantos/dlp-friends@sha256:[a-f0-9]{64}", image), "immutable_image_required")
        ops.require(re.fullmatch(r"[a-f0-9]{40}", commit), "exact_commit_required")
        ops.run(["docker", "pull", "--platform", "linux/amd64", image])
        info = json.loads(ops.run(["docker", "image", "inspect", image]))[0]
        ops.require(info["Os"] == "linux" and info["Architecture"] == "amd64", "amd64_image_required")
        labels = info["Config"]["Labels"]
        tree, migrations = labels.get("io.dlp-friends.source-tree", ""), labels.get("io.dlp-friends.migrations-sha256", "")
        ops.require(labels.get("org.opencontainers.image.revision") == commit
                    and re.fullmatch(r"[a-f0-9]{40}", tree)
                    and re.fullmatch(r"[a-f0-9]{64}", migrations), "image_metadata_invalid")
        actual = ops.run(["docker", "run", "--rm", "--network", "none", "--entrypoint", "sh", image, "-c",
                          "find database/migrations -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum"]).split()[0]
        ops.require(actual == migrations, "embedded_migrations_mismatch")
        return {"commit": commit, "tree": tree, "migrations": migrations}

    def fresh(self, created):
        ops.require(isinstance(created, int) and 0 <= time.time() - created < 86400, "snapshot_expired")

    def rehearsal(self, metadata):
        report = self.read("rehearsal.json")
        self.fresh(report["snapshot_created_at"])
        ops.require(report.get("status") == "passed" and report.get("second_run_noop") is True
                    and report.get("journeys_passed") is True and isinstance(report.get("applied_migrations"), list)
                    and report.get("snapshot_id") and isinstance(report.get("completed_at"), int)
                    and report["snapshot_created_at"] <= report["completed_at"] <= time.time(), "rehearsal_incomplete")
        self.fresh(report["snapshot_created_at"])
        ops.require(report["candidate_tree"] == metadata["tree"]
                    and report["migration_sha256"] == metadata["migrations"], "content_mismatch")
        ops.require(self.schema_fingerprint("source") == report["source_schema_sha256"], "source_schema_changed")
        return report

    def journeys(self, name, binding, earliest, checks=JOURNEYS):
        report = self.read(name)
        ops.require(all(report.get(key) == value for key, value in binding.items())
                    and isinstance(report.get("completed_at"), int) and earliest <= report["completed_at"] <= time.time()
                    and all(report.get("checks", {}).get(key) is True for key in checks), "journey_attestation_required")

    def beta_command(self, image, *arguments):
        target = self.config["beta"]
        private_env = ops.private_file(target["env_file"])
        uuid = self.mysql("beta", "SELECT @@server_uuid, DATABASE();").strip().split("\t")[0]
        # Evaluate the image's actual configuration before permitting any Artisan action.
        php = ('require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
               '$kernel=$app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); '
               'if (!app()->environment("beta") || !app()->isDownForMaintenance() || '
               'config("database.connections.mysql.database") !== ' + json.dumps(target["database"]) + ' || '
               'Illuminate\\Support\\Facades\\DB::selectOne("SELECT @@server_uuid AS uuid")->uuid !== ' + json.dumps(uuid) + ' || '
               'config("filesystems.disks.s3.bucket") !== ' + json.dumps(target["bucket"]) + ' || '
               'config("filesystems.disks.s3.key") !== ' + json.dumps(target["storage_key_id"]) + ') exit(78); '
               '$app->make(App\\Support\\DeploymentReadiness::class)->assertBetaConfiguration(); '
               'exit($kernel->handle(new Symfony\\Component\\Console\\Input\\ArgvInput(array_merge(["artisan"], '
               + json.dumps(list(arguments)) + ')), new Symfony\\Component\\Console\\Output\\ConsoleOutput()));')
        return ops.run(["docker", "run", "--rm", "--network", target["network"], "--env-file", str(private_env),
                        "--entrypoint", "php", image, "-r", php])

    def rehearse(self, image, commit):
        snapshot = self.read("snapshot.json")
        self.fresh(snapshot["created_at"])
        ops.require(all(snapshot.get(key) is True for key in ("tables_verified", "objects_verified", "sanitized")), "snapshot_unverified")
        self.validate_targets()
        metadata = self.image(image, commit)
        ops.require(self.schema_fingerprint("source") == snapshot["source_schema_sha256"], "source_schema_changed")
        path = self.directory / "rehearsal.json"
        previous = self.read("rehearsal.json") if path.exists() else {}
        binding = {"snapshot_id": snapshot["snapshot_id"], "candidate_image": image, "candidate_commit": commit}
        report = {**binding, "snapshot_created_at": snapshot["created_at"], "candidate_tree": metadata["tree"],
                  "migration_sha256": metadata["migrations"], "source_schema_sha256": snapshot["source_schema_sha256"],
                  "applied_migrations": [], "second_run_noop": False, "journeys_passed": False, "status": "failed"}
        if previous.get("status") == "awaiting_journeys" and all(previous.get(k) == v for k, v in binding.items()):
            report = previous
            ops.require(self.schema_fingerprint("beta") == report["beta_schema_sha256"], "rehearsed_schema_changed")
        else:
            ops.write_json(path, report)
            before = self.mysql("beta", "SELECT migration, batch FROM migrations ORDER BY migration;")
            started = time.monotonic()
            self.beta_command(image, "migrate", "--force", "--no-interaction")
            after = self.mysql("beta", "SELECT migration, batch FROM migrations ORDER BY migration;")
            self.beta_command(image, "migrate", "--force", "--no-interaction")
            ops.require(self.mysql("beta", "SELECT migration, batch FROM migrations ORDER BY migration;") == after, "second_migration_not_noop")
            self.beta_command(image, "deployment:check", "--allow-maintenance", "--json")
            report.update(applied_migrations=sorted(set(after.splitlines()) - set(before.splitlines())), second_run_noop=True,
                          duration_seconds=round(time.monotonic() - started, 3), migrated_at=int(time.time()),
                          beta_schema_sha256=self.schema_fingerprint("beta"), status="awaiting_journeys")
            ops.write_json(path, report)
        self.journeys("beta-journeys.json", binding, report["migrated_at"])
        self.beta_command(image, "deployment:check", "--allow-maintenance", "--json")
        report.update(journeys_passed=True, completed_at=int(time.time()), status="passed")
        ops.write_json(path, report)

    def runtime(self):
        php = ('require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
               '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
               'echo json_encode(["env"=>app()->environment(),"closed"=>app()->isDownForMaintenance(),'
               '"driver"=>config("app.maintenance.driver"),"store"=>config("app.maintenance.store"),'
               '"prefix"=>config("cache.prefix"),"database"=>config("database.connections.mysql.database"),'
               '"mysql_server_uuid"=>Illuminate\\Support\\Facades\\DB::selectOne("SELECT @@server_uuid AS uuid")->uuid]);')
        result = json.loads(ops.run(["docker", "exec", self.config["source"]["web_container"], "php", "-r", php]))
        actual_uuid = self.mysql("source", "SELECT @@server_uuid, DATABASE();").strip().split("\t")[0]
        ops.require(result["env"] == "production" and result["driver"] == "cache" and result["store"] == "redis"
                    and result["prefix"] == "dlp_friends_production_cache_"
                    and result["database"] == self.config["source"]["database"]
                    and result["mysql_server_uuid"] == actual_uuid, "persistent_production_maintenance_required")
        return result

    def closed(self):
        ops.require(self.runtime()["closed"] is True, "production_not_closed")

    def drained(self, image=None):
        containers = self.config["source"]["process_containers"]
        ops.require(len(containers) == 3 and len(set(containers)) == 3, "all_processes_required")
        for name in containers:
            info = self.inspect(name)
            if image is not None:
                ops.require(info["Config"]["Image"] == image, "process_image_mismatch")
            state = info["State"]
            ops.require(state["Running"] is False and (state["ExitCode"] == 0 or (image is not None and state["ExitCode"] == 75)), "production_processes_not_drained")

    def running_image(self):
        info = self.inspect(self.config["source"]["web_container"])
        ops.require(info["State"]["Running"] is True and info["State"].get("Health", {}).get("Status") == "healthy", "web_not_healthy")
        return info["Config"]["Image"]

    def verified_backup(self, authorization):
        backup = self.read("backup.json")
        sql = ops.private_file(self.directory / backup["sql_file"])
        with sql.open("rb") as file:
            actual = file_hash(file)
        ops.require(backup.get("status") == "verified" and actual == backup["sql_sha256"]
                    and backup["backup_id"] == authorization["backup_id"], "verified_backup_required")
        self.journeys_backup(backup)

    def journeys_backup(self, backup):
        attestation = self.read("backup-restore.json")
        ops.require(attestation.get("backup_id") == backup["backup_id"]
                    and attestation.get("sql_sha256") == backup["sql_sha256"]
                    and attestation.get("objects_sha256") == backup["objects_sha256"]
                    and attestation.get("restored_to_isolated_database") is True
                    and attestation.get("schema_and_counts_verified") is True
                    and attestation.get("objects_download_verified") is True
                    and backup["created_at"] <= attestation.get("completed_at", 0) <= time.time(), "verified_backup_required")

    def prepare(self, image, commit):
        metadata = self.image(image, commit)
        report = self.rehearsal(metadata)
        (self.directory / "authorization.json").unlink(missing_ok=True)
        self.runtime()  # Old image must already share the persistent maintenance store.
        backup_path = self.directory / "backup.json"
        if backup_path.exists() and self.read("backup.json").get("status") == "awaiting_restore_verification":
            backup = self.read("backup.json")
            ops.require(backup["image"] == image and backup["commit"] == commit, "backup_candidate_mismatch")
            self.closed()
            self.drained()
        else:
            previous = self.running_image()
            ops.require(re.fullmatch(r"ghcr\.io/zdossantos/dlp-friends@sha256:[a-f0-9]{64}", previous), "previous_digest_required")
            previous_labels = json.loads(ops.run(["docker", "image", "inspect", previous]))[0]["Config"]["Labels"]
            self.artisan("source", "down", "--with-secret", "--retry=60")
            # The bypass URL is deliberately discarded; generate a separate private bypass manually for operator journeys.
            for name in self.config["source"]["process_containers"]:
                ops.run(["docker", "stop", "--time", "150", name])
            self.closed()
            self.drained()
            ops.require(self.schema_fingerprint("source") == report["source_schema_sha256"], "source_schema_changed")
            identifier = os.urandom(16).hex()
            sql = self.directory / (identifier + ".sql")
            with sql.open("wb") as output:
                ops.run(["mysqldump", "--defaults-extra-file=" + self.config["source"]["mysql_client"], "--single-transaction",
                         "--quick", "--hex-blob", "--no-tablespaces", "--set-gtid-purged=OFF", "--skip-triggers",
                         self.config["source"]["database"]], output=output)
            with sql.open("rb") as file:
                sql_hash = file_hash(file)
            objects = self.directory / (identifier + "-objects")
            objects.mkdir(mode=0o700)
            source = self.config["source"]
            origin = source["rclone_remote"] + ":" + source["bucket"]
            ops.run(["rclone", "--config", self.config["rclone_config"], "copy", origin, str(objects)])
            ops.run(["rclone", "--config", self.config["rclone_config"], "check", "--download", origin, str(objects)])
            backup = {"backup_id": identifier, "created_at": int(time.time()), "sql_file": sql.name, "sql_sha256": sql_hash,
                      "objects_sha256": ops.digest(json.dumps(self.inventory("source"), sort_keys=True)),
                      "previous_image": previous, "previous_commit": previous_labels.get("org.opencontainers.image.revision"),
                      "image": image, "commit": commit, "status": "awaiting_restore_verification"}
            ops.write_json(backup_path, backup)
        self.journeys_backup(backup)
        backup["status"] = "verified"
        ops.write_json(backup_path, backup)
        authorization = {"status": "prepared", "image": image, "commit": commit, "backup_id": backup["backup_id"],
                         "rehearsal_sha256": ops.digest(json.dumps(report, sort_keys=True)), "prepared_at": int(time.time())}
        self.verified_backup(authorization)
        ops.write_json(self.directory / "authorization.json", authorization)

    def authorization(self, image, commit, status):
        authorization = self.read("authorization.json")
        ops.require(authorization.get("status") == status and authorization.get("image") == image
                    and authorization.get("commit") == commit, "verified_release_required")
        return authorization

    def coolify(self, method, path, body=None):
        endpoint = self.config["coolify_api_url"].rstrip("/")
        ops.require(endpoint.startswith("https://") or endpoint.startswith("http://127.0.0.1:"), "private_api_transport_required")
        config = ops.private_file(self.config["coolify_curl_config"])
        arguments = ["curl", "--config", str(config), "--silent", "--fail", "--connect-timeout", "10", "--max-time", "60",
                     "--request", method, "--header", "Content-Type: application/json"]
        if body is not None:
            arguments += ["--data-binary", "@-"]
        return json.loads(ops.run(arguments + [endpoint + path], input=json.dumps(body).encode() if body is not None else None))

    def deploy(self, image, commit):
        metadata = self.image(image, commit)
        report = self.rehearsal(metadata)
        authorization = self.authorization(image, commit, "prepared")
        ops.require(authorization["rehearsal_sha256"] == ops.digest(json.dumps(report, sort_keys=True)), "rehearsal_changed")
        self.closed()
        self.drained()
        self.verified_backup(authorization)
        app = self.config["source"]["application_uuid"]
        ops.require(re.fullmatch(r"[A-Za-z0-9_-]+", app) and app != self.config["beta"]["application_uuid"], "resource_alias")
        # Consume before the first network mutation. Timeout leaves unknown state, never retries.
        authorization["status"] = "deployment_unknown"
        ops.write_json(self.directory / "authorization.json", authorization)
        self.coolify("PATCH", "/applications/" + app + "/envs/bulk", {"data": [{"key": "APP_IMAGE", "value": image,
                     "is_preview": False, "is_literal": True, "is_buildtime": True, "is_runtime": True}]})
        self.coolify("PATCH", "/applications/" + app, {"git_commit_sha": commit})
        result = self.coolify("POST", "/deploy", {"uuid": app, "force": False})
        deployments = result.get("deployments", [])
        ops.require(len(deployments) == 1 and deployments[0].get("resource_uuid") == app
                    and re.fullmatch(r"[A-Za-z0-9_-]+", deployments[0].get("deployment_uuid", "")), "deployment_not_confirmed")
        authorization.update(status="deployed", deployment_uuid=deployments[0]["deployment_uuid"])
        ops.write_json(self.directory / "authorization.json", authorization)

    def verify(self, image, commit):
        authorization = self.authorization(image, commit, "deployed")
        deployment = self.coolify("GET", "/deployments/" + authorization["deployment_uuid"])
        ops.require(deployment.get("status") == "finished" and deployment.get("commit") == commit, "deployment_not_finished")
        ops.require(self.running_image() == image, "running_image_mismatch")
        self.closed()
        self.artisan("source", "deployment:check", "--allow-maintenance", "--json")
        self.journeys("production-journeys.json", {"image": image, "commit": commit,
                       "deployment_uuid": authorization["deployment_uuid"]}, authorization["prepared_at"], ("login", "existing_profile", "discovery", "file"))
        authorization.update(status="verified", verified_at=int(time.time()))
        ops.write_json(self.directory / "authorization.json", authorization)

    def resume(self, image, commit, confirmed):
        authorization = self.authorization(image, commit, "verified")
        ops.require(confirmed, "explicit_resume_required")
        ops.require(0 <= time.time() - authorization["verified_at"] < 3600, "verification_expired")
        ops.require(self.running_image() == image, "running_image_mismatch")
        self.closed()
        self.drained(image)
        self.artisan("source", "deployment:check", "--allow-maintenance", "--json")
        for name in self.config["source"]["process_containers"]:
            ops.run(["docker", "start", name])
            ops.require(self.inspect(name)["State"]["Running"] is True, "process_start_failed")
        # Their entrypoint waits for maintenance to end, then rechecks schema before executing.
        self.artisan("source", "up")
        authorization.update(status="resumed", resumed_at=int(time.time()))
        ops.write_json(self.directory / "authorization.json", authorization)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument("phase", choices=("rehearse", "prepare", "deploy", "verify", "resume"))
    parser.add_argument("--config", required=True)
    parser.add_argument("--image", required=True)
    parser.add_argument("--commit", required=True)
    parser.add_argument("--confirm-resume", action="store_true")
    args = parser.parse_args()
    release = Release(args.config)
    with release.lock():
        if args.phase == "resume":
            release.resume(args.image, args.commit, args.confirm_resume)
        else:
            getattr(release, args.phase)(args.image, args.commit)
    print("phase_completed")


if __name__ == "__main__":
    try:
        main()
    except (Exception, KeyboardInterrupt):
        print("phase_failed_no_automatic_resume_or_rollback", file=sys.stderr)
        sys.exit(1)
