#!/usr/bin/env bash
set -euo pipefail

base_ref="${BASE_REF:-refs/remotes/origin/main}"
source_ref="${SOURCE_REF:-refs/remotes/origin/develop}"
promotion_branch="${PROMOTION_BRANCH:-automation/promote-develop}"
output_file="${GITHUB_OUTPUT:-/dev/null}"

if [[ "$(git rev-parse --is-shallow-repository)" == "true" ]]; then
    echo "Promotion requires a complete Git history." >&2
    exit 1
fi

git rev-parse --verify "$base_ref^{commit}" >/dev/null
git rev-parse --verify "$source_ref^{commit}" >/dev/null

if git merge-base --is-ancestor "$source_ref" "$base_ref"; then
    echo "promotion_required=false" >> "$output_file"
    exit 0
fi

git switch --force-create "$promotion_branch" "$base_ref"
git merge --no-ff --no-edit "$source_ref"
git merge-base --is-ancestor "$base_ref" HEAD
git merge-base --is-ancestor "$source_ref" HEAD
echo "promotion_required=true" >> "$output_file"
