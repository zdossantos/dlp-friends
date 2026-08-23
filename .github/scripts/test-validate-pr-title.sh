#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/validate-pr-title.sh"

valid_titles=(
    "feat: add member favorites"
    "fix(auth): preserve the session"
    "feat!: change the public contract"
    "feat(api)!: change the scoped contract"
    "chore(deps): bump locked dependencies"
    "revert: restore the previous behavior"
)

invalid_titles=(
    "Add member favorites"
    "Feat: use an uppercase type"
    "feature: use an unsupported type"
    "fix(auth) missing colon"
    "fix:"
    "fix:  "
    $'fix: \t'
    " fix: leading whitespace"
)

for title in "${valid_titles[@]}"; do
    "$subject" "$title"
done

for title in "${invalid_titles[@]}"; do
    if "$subject" "$title" >/dev/null 2>&1; then
        echo "Expected title to be rejected: $title" >&2
        exit 1
    fi
done

if "$subject" >/dev/null 2>&1; then
    echo "Expected a missing title to be rejected" >&2
    exit 1
fi

echo "Pull request title validation tests passed."
