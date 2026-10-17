"""Exercise the actual release gate; only external I/O is simulated."""
import importlib.util
import json
import os
os.umask(0o077)
import sys
import subprocess
import tempfile
import time
from pathlib import Path

root = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(root / "scripts/operations"))
spec = importlib.util.spec_from_file_location("release", root / "scripts/operations/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
ops = release.ops
scenario = sys.argv[1]
image = "ghcr.io/zdossantos/dlp-friends@sha256:" + "a" * 64
commit, tree, migrations, schema = "b" * 40, "c" * 40, "d" * 64, "e" * 64
mutations = []
started = set()
with tempfile.TemporaryDirectory() as temporary:
    directory = Path(temporary)
    directory.chmod(0o700)
    gate = object.__new__(release.Release)
    gate.directory = directory
    gate.config = {"source": {"application_uuid": "production", "web_container": "web",
                             "database": "production",
                             "process_containers": ["worker", "scheduler", "reverb"]},
                   "beta": {"application_uuid": "beta", "network": "beta-network", "database": "beta", "bucket": "beta", "storage_key_id": "beta-key", "env_file": str(directory / "beta.env")}}
    (directory / "beta.env").write_text("SYNTHETIC=true\n")
    report = {"status": "passed", "snapshot_id": "snapshot", "snapshot_created_at": int(time.time()),
              "candidate_commit": "f" * 40, "candidate_tree": tree, "candidate_image": image,
              "migration_sha256": migrations, "source_schema_sha256": schema,
              "applied_migrations": ["create_users"], "second_run_noop": True,
              "journeys_passed": True, "completed_at": int(time.time())}
    if scenario == "failed-report": report["status"] = "failed"
    # A new candidate after a passed rehearsal, or an interrupted previous run,
    # must both start from the snapshot's original schema/migration register.
    if scenario == "dirty-beta-migrations": report["status"] = "failed"
    if scenario == "expired-snapshot": report["snapshot_created_at"] -= 86400
    if scenario == "future-snapshot": report["snapshot_created_at"] += 60
    if scenario == "different-tree": report["candidate_tree"] = "0" * 40
    if scenario == "different-migrations": report["migration_sha256"] = "0" * 64
    if scenario != "missing-report": ops.write_json(directory / "rehearsal.json", report)
    actual = {"tree": tree, "migrations": migrations, "commit": commit}
    def fake_run(args, **kwargs):
        if args[:3] == ["docker", "exec", "web"]:
            if args[3:5] == ["php", "-r"]:
                lint = subprocess.run(["php", "-l"], input="<?php " + args[-1], text=True, capture_output=True)
                assert lint.returncode == 0, lint.stderr + lint.stdout
                return json.dumps({"env": "production", "closed": True, "driver": "cache", "store": "redis",
                                   "prefix": "dlp_friends_production_cache_", "database": "production", "mysql_server_uuid": "mysql-server",
                                   "redis_server_id": "other" if scenario == "runtime-wrong-server" else "redis-server",
                                   "redis_database": 2 if scenario == "runtime-wrong-database" else 1,
                                   "redis_prefix": "old-prefix" if scenario == "runtime-wrong-prefix" else "dlp_friends_production_"})
            assert args[3:5] == ["sh", "-c"]
            if args[-1].startswith('! pgrep'):
                return ""
            assert "QUIT" in args[-1] and "pgrep" in args[-1]
            mutations.append("http-drained")
            return ""
        if args[:3] == ["docker", "image", "inspect"]:
            return json.dumps([{"Os": "linux", "Architecture": "arm64" if scenario == "wrong-platform" else "amd64",
                                "Config": {"Labels": {"org.opencontainers.image.revision": commit,
                                "io.dlp-friends.source-tree": tree, "io.dlp-friends.migrations-sha256": migrations}}}])
        if args[:2] == ["docker", "pull"]: return ""
        if args[:2] == ["docker", "run"]:
            if "php" in args:
                lint = subprocess.run(["php", "-l"], input="<?php " + args[-1], text=True, capture_output=True)
                assert lint.returncode == 0, lint.stderr + lint.stdout
                if '\"migrate\"' in args[-1]: mutations.append("beta-migrate")
                return ""
            return migrations + "  -\n"
        if args[:2] == ["docker", "start"]:
            mutations.append("start")
            started.add(args[2])
            return ""
        raise AssertionError(args)
    ops.run = fake_run
    gate.mysql = lambda side, sql, **kwargs: ("mysql-server\tproduction\n" if "@@server_uuid" in sql else
        "changed\t1\n" if scenario == "dirty-beta-migrations" else "create_users\t1\n")
    gate.redis_identity = lambda side: "redis-server"
    gate.schema_fingerprint = lambda side: "0" * 64 if scenario == "changed-schema" or (side == "beta" and scenario == "dirty-beta-schema") else schema
    gate.inspect = lambda name: {"Config": {"Image": "wrong-image" if scenario == "old-background-image" else image}, "State": {"Running": scenario == "active-process" or name in started, "ExitCode": 0}}
    gate.closed = lambda: ops.require(scenario != "maintenance-absent", "production_not_closed")
    def artisan(*args):
        if scenario == "readiness-failed": raise ops.OperationError("readiness_failed")
        if "up" in args: mutations.append("up")
        return ""
    gate.artisan = artisan
    def api(*args):
        mutations.append("api")
        if scenario == "coolify-timeout": raise ops.OperationError("external_operation_failed")
        return {"deployments": [{"resource_uuid": "production", "deployment_uuid": "deployment"}]}
    gate.coolify = api
    expected = {"missing-report": "private_report_missing", "failed-report": "rehearsal_incomplete",
                "expired-snapshot": "snapshot_expired", "future-snapshot": "snapshot_expired",
                "different-tree": "content_mismatch", "different-migrations": "content_mismatch",
                "changed-schema": "source_schema_changed", "mutable-image": "immutable_image_required",
                "wrong-platform": "amd64_image_required", "active-process": "production_processes_not_drained",
                "maintenance-absent": "production_not_closed", "unverified-backup": "verified_backup_required",
                "resume-without-verification": "verified_release_required", "coolify-timeout": "external_operation_failed",
                "migration-failed": "external_operation_failed", "readiness-failed": "readiness_failed", "old-background-image": "process_image_mismatch"}
    expected.update({key: "persistent_production_maintenance_required" for key in
                     ("runtime-wrong-prefix", "runtime-wrong-database", "runtime-wrong-server")})
    expected.update({"dirty-beta-schema": "fresh_beta_snapshot_required", "dirty-beta-migrations": "fresh_beta_snapshot_required"})
    try:
        if scenario.startswith("runtime-"):
            gate.runtime()
            assert scenario == "runtime-valid", "unsafe maintenance namespace accepted"
        elif scenario == "http-drain":
            gate.drain_http()
            assert mutations == ["http-drained"]
        elif scenario in ("resume-success", "old-background-image"):
            ops.write_json(directory / "authorization.json", {"status": "verified", "image": image, "commit": commit, "verified_at": int(time.time())})
            gate.running_image = lambda: image
            gate.resume(image, commit, True)
            assert mutations == ["start", "start", "start", "up"]
            assert json.loads((directory / "authorization.json").read_text())["status"] == "resumed"
        elif scenario in ("rehearse-success", "missing-journeys", "dirty-beta-schema", "dirty-beta-migrations"):
            gate.validate_targets = lambda: None
            ops.write_json(directory / "snapshot.json", {"snapshot_id": "snapshot", "created_at": int(time.time()),
                           "source_schema_sha256": schema, "source_migrations_sha256": ops.digest("create_users\t1\n"),
                           "tables_verified": True, "objects_verified": True, "sanitized": True})
            if scenario == "rehearse-success":
                (directory / "beta-journeys.json").unlink(missing_ok=True)
                try:
                    gate.rehearse(image, commit)
                    raise AssertionError("journeys must be attested after migrations")
                except ops.OperationError as error:
                    assert str(error) == "private_report_missing"
                ops.write_json(directory / "beta-journeys.json", {"snapshot_id": "snapshot", "candidate_image": image,
                               "candidate_commit": commit, "completed_at": int(time.time()),
                               "checks": {key: True for key in release.JOURNEYS}})
            try:
                gate.rehearse(image, commit)
                assert scenario == "rehearse-success"
                assert json.loads((directory / "rehearsal.json").read_text())["status"] == "passed"
            except ops.OperationError as error:
                if scenario.startswith("dirty-beta-"):
                    raise
                assert scenario == "missing-journeys" and str(error) == "private_report_missing"
                assert json.loads((directory / "rehearsal.json").read_text())["status"] == "awaiting_journeys"
            assert mutations == ["beta-migrate", "beta-migrate"]
        elif scenario == "resume-without-verification":
            ops.write_json(directory / "authorization.json", {"status": "deployed", "image": image, "commit": commit})
            gate.resume(image, commit, False)
        elif scenario in ("active-process", "maintenance-absent", "unverified-backup", "coolify-timeout", "deploy-success"):
            ops.write_json(directory / "authorization.json", {"status": "prepared", "image": image, "commit": commit,
                           "rehearsal_sha256": ops.digest(json.dumps(report, sort_keys=True)), "backup_verified": scenario != "unverified-backup"})
            gate.verified_backup = lambda auth: ops.require(auth["backup_verified"], "verified_backup_required")
            gate.deploy(image, commit)
            assert scenario == "deploy-success" and mutations == ["api", "api", "api"]
        elif scenario == "migration-failed":
            gate.validate_targets = lambda: None
            gate.image = lambda *args: actual
            ops.write_json(directory / "snapshot.json", {"snapshot_id": "snapshot", "created_at": int(time.time()),
                           "source_schema_sha256": schema, "source_migrations_sha256": ops.digest("create_users\t1\n"),
                           "tables_verified": True, "objects_verified": True, "sanitized": True})
            gate.beta_command = lambda *args: (_ for _ in ()).throw(ops.OperationError("external_operation_failed"))
            gate.rehearse(image, commit)
        elif scenario == "readiness-failed":
            ops.write_json(directory / "authorization.json", {"status": "deployed", "image": image, "commit": commit, "deployment_uuid": "deployment"})
            gate.coolify = lambda *args: {"status": "finished", "commit": commit}
            gate.running_image = lambda: image
            gate.verify(image, commit)
        else:
            metadata = gate.image(image if scenario != "mutable-image" else "ghcr.io/zdossantos/dlp-friends:latest", commit)
            gate.rehearsal(metadata)
            assert scenario == "squashed-equivalent-tree", "unsafe release accepted"
    except ops.OperationError as error:
        assert str(error) == expected[scenario], str(error)
        if scenario == "migration-failed": assert json.loads((directory / "rehearsal.json").read_text())["status"] != "passed"
        if scenario == "coolify-timeout":
            assert mutations == ["api"]
            assert json.loads((directory / "authorization.json").read_text())["status"] == "deployment_unknown"
        else: assert not mutations, mutations
    if scenario != "resume-success": assert "start" not in mutations
