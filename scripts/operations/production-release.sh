#!/usr/bin/env bash
set -euo pipefail
umask 077
exec python3 "$(dirname "$0")/release.py" "$@"
