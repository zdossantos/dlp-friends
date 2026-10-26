#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${BETA_SSH_HOST:?BETA_SSH_HOST is required}"
: "${BETA_SSH_PRIVATE_KEY:?BETA_SSH_PRIVATE_KEY is required}"
: "${BETA_SSH_KNOWN_HOSTS:?BETA_SSH_KNOWN_HOSTS is required}"
[[ "$BETA_SSH_HOST" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*$ ]] || exit 1
directory="$(mktemp -d)"
trap 'rm -rf "$directory"' EXIT
printf '%s\n' "$BETA_SSH_PRIVATE_KEY" > "$directory/key"
printf '%s\n' "$BETA_SSH_KNOWN_HOSTS" > "$directory/known_hosts"
ssh -i "$directory/key" -o BatchMode=yes -o IdentitiesOnly=yes \
    -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$directory/known_hosts" \
    -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=3 \
    "dlp-beta-deploy@$BETA_SSH_HOST"
