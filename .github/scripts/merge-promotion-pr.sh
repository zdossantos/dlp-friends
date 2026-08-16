#!/usr/bin/env bash
set -euo pipefail

promotion_head="automation/promote-develop"
promotion_base="main"

pr_json="$(gh pr list \
    --state open \
    --base "$promotion_base" \
    --head "$promotion_head" \
    --json number,headRefName,baseRefName,headRefOid,isDraft,mergeStateStatus)"

pr_count="$(jq 'length' <<< "$pr_json")"
if [[ "$pr_count" -ne 1 ]]; then
    echo "Expected exactly one open $promotion_head -> $promotion_base pull request; found $pr_count." >&2
    exit 1
fi

pr_number="$(jq -r '.[0].number' <<< "$pr_json")"
head_ref="$(jq -r '.[0].headRefName' <<< "$pr_json")"
base_ref="$(jq -r '.[0].baseRefName' <<< "$pr_json")"
head_sha="$(jq -r '.[0].headRefOid' <<< "$pr_json")"
is_draft="$(jq -r '.[0].isDraft' <<< "$pr_json")"
merge_state="$(jq -r '.[0].mergeStateStatus' <<< "$pr_json")"

if [[ "$head_ref" != "$promotion_head" || "$base_ref" != "$promotion_base" ]]; then
    echo "Refusing unexpected pull request $head_ref -> $base_ref." >&2
    exit 1
fi

if [[ "$is_draft" != "false" ]]; then
    echo "Pull request #$pr_number is still a draft." >&2
    exit 1
fi

if [[ "$merge_state" != "CLEAN" ]]; then
    echo "Pull request #$pr_number is not clean: $merge_state." >&2
    exit 1
fi

gh pr checks "$pr_number" --required

current_head_sha="$(gh pr view "$pr_number" --json headRefOid --jq '.headRefOid')"
if [[ "$current_head_sha" != "$head_sha" ]]; then
    echo "Pull request #$pr_number changed from $head_sha to $current_head_sha; run the workflow again after checks pass." >&2
    exit 1
fi

gh pr merge "$pr_number" --merge --match-head-commit "$head_sha"

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
    printf '## Production promotion\n\nMerged PR #%s at `%s` with a merge commit.\n' \
        "$pr_number" "$head_sha" >> "$GITHUB_STEP_SUMMARY"
fi
