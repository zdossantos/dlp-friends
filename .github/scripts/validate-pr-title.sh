#!/usr/bin/env bash
set -euo pipefail

title="${1:-}"
pattern='^(feat|fix|perf|refactor|docs|test|build|ci|chore|revert)(\([a-z0-9][a-z0-9._/-]*\))?!?: .+$'

if [[ ! "$title" =~ $pattern ]]; then
    echo "Pull request title must follow Conventional Commits, for example: feat(scope): describe the change" >&2
    exit 1
fi
