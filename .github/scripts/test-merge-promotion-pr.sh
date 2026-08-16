#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/merge-promotion-pr.sh"
test_root="$(mktemp -d)"
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/bin"

cat > "$test_root/bin/gh" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail

printf '%q ' "$@" >> "$GH_CALLS"
printf '\n' >> "$GH_CALLS"

case "$1 $2" in
  "pr list")
    case "$GH_SCENARIO" in
      absent)
        printf '[]\n'
        ;;
      multiple)
        printf '[{"number":36},{"number":37}]\n'
        ;;
      wrong-refs)
        printf '[{"number":36,"headRefName":"feature/untrusted","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"CLEAN"}]\n'
        ;;
      draft)
        printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":true,"mergeStateStatus":"CLEAN"}]\n'
        ;;
      dirty)
        printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"DIRTY"}]\n'
        ;;
      *)
        printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"CLEAN"}]\n'
        ;;
    esac
    ;;
  "pr checks")
    [[ "$GH_SCENARIO" != "checks-fail" ]]
    ;;
  "pr view")
    if [[ "$GH_SCENARIO" == "head-changed" ]]; then
      printf 'def456\n'
    else
      printf 'abc123\n'
    fi
    ;;
  "pr merge")
    ;;
  *)
    exit 64
    ;;
esac
MOCK
chmod +x "$test_root/bin/gh"

run_subject() {
    local scenario="$1"

    : > "$test_root/calls"
    : > "$test_root/summary"
    PATH="$test_root/bin:$PATH" \
        GH_SCENARIO="$scenario" \
        GH_CALLS="$test_root/calls" \
        GH_REPO="zdossantos/dlp-friends" \
        GH_TOKEN="test-token" \
        GITHUB_STEP_SUMMARY="$test_root/summary" \
        "$subject" > "$test_root/output" 2>&1
}

assert_rejected_without_merge() {
    local scenario="$1"

    if run_subject "$scenario"; then
        echo "Expected scenario $scenario to fail" >&2
        exit 1
    fi

    if grep -q '^pr merge ' "$test_root/calls"; then
        echo "Scenario $scenario attempted a merge" >&2
        exit 1
    fi
}

for scenario in absent multiple wrong-refs draft dirty checks-fail head-changed; do
    assert_rejected_without_merge "$scenario"
done

run_subject success
grep -q '^pr list .*--base main .*--head automation/promote-develop ' "$test_root/calls"
grep -qx 'pr checks 36 --required ' "$test_root/calls"
grep -qx 'pr merge 36 --merge --match-head-commit abc123 ' "$test_root/calls"
grep -q 'Merged PR #36 at `abc123` with a merge commit' "$test_root/summary"

workflow="$script_dir/../workflows/promote-to-production.yml"
promotion_workflow="$script_dir/../workflows/promote-develop.yml"
grep -q '^name: Promote to production$' "$workflow"
grep -q '^  workflow_dispatch:$' "$workflow"
grep -q 'RELEASE_PLEASE_TOKEN' "$workflow"
grep -q 'run: .github/scripts/merge-promotion-pr.sh' "$workflow"
grep -q 'Promote to production' "$promotion_workflow"
grep -q 'gh pr edit' "$promotion_workflow"
if grep -q 'Create a merge commit' "$promotion_workflow"; then
    echo "Promotion PR still recommends the unsafe standard merge button" >&2
    exit 1
fi

echo "Guarded promotion merge tests passed."
