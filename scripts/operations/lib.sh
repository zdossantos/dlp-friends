#!/usr/bin/env bash
set -euo pipefail
umask 077
OPERATIONS_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
validate_targets() { python3 "$OPERATIONS_DIR/operations.py" validate-targets --config "$1"; }
schema_fingerprint() { python3 "$OPERATIONS_DIR/operations.py" schema-fingerprint --config "$1" --side "$2"; }
migration_fingerprint() {
    docker run --rm --entrypoint sh "$1" -c 'find database/migrations -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum' | cut -d ' ' -f 1
}
