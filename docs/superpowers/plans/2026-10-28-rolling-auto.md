# Automatic rolling release implementation plan

**Goal:** merging the Release Please PR deploys the exact published release, including compatible migrations and all application processes.
**Architecture:** GitHub sends a bounded JSON request over a forced SSH command. A root-owned server script validates the immutable release, existing topology, migration compatibility and health; migrates once, rolls web through Coolify, then reconciles support. No maintenance mode, database rollback, dependency restart or data import.
**Tech stack:** existing Python standard library, SSH, Docker, Coolify API, GitHub Actions.
**Spec:** user-approved sequence and 1.16.2 maintenance scope in this conversation.

## Constraints

- Start at v1.16.1 and carry existing rolling fixes; exclude all 1.17 business changes.
- Forward-port to main through a PR; no force push, manual tag or release.
- Production never stops for setup/tests. New sensitive SSH access requires approval at installation.
- Automatic migration requires an unchanged old migration set and reviewed SHA256 declarations for additions; no automatic destructive rollback.
- Keep exactly one scheduler, drain workers, preserve sessions/assets/data and report support failures honestly.

## Review focus

Malformed requests; stale/out-of-order releases; migration failure and partial application; readiness failure keeping old web; support update failure and uncertain deployment admission.

## Tasks

- [ ] Add failing orchestration tests for success/order, rejected inputs, failed migration, health failure, partial support failure, serialization and migration approval.
- [ ] Implement private server orchestration and bounded SSH request; integrate release workflow with explicit branch gate and shared deployment concurrency.
- [ ] Run infrastructure suite, runtime checks and CI; forward-port to main without including its unreleased features in maintenance.
- [ ] Install and validate relay on server only after required access approval; beta rehearsal before production activation. Record evidence and remaining limitations.
