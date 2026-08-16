#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/prepare-promotion-branch.sh"
test_root="$(mktemp -d)"
trap 'rm -rf "$test_root"' EXIT

assert_ancestor() {
    local repository="$1"
    local ancestor="$2"
    local descendant="$3"

    git -C "$repository" merge-base --is-ancestor "$ancestor" "$descendant" || {
        echo "Expected $ancestor to be an ancestor of $descendant" >&2
        exit 1
    }
}

git init --quiet --bare "$test_root/origin.git"
git init --quiet -b main "$test_root/seed"
git -C "$test_root/seed" config user.name "Promotion Test"
git -C "$test_root/seed" config user.email "promotion-test@example.com"
echo initial > "$test_root/seed/app.txt"
git -C "$test_root/seed" add app.txt
git -C "$test_root/seed" commit --quiet -m "chore: initialize test repository"
git -C "$test_root/seed" remote add origin "$test_root/origin.git"
git -C "$test_root/seed" push --quiet -u origin main
git --git-dir="$test_root/origin.git" symbolic-ref HEAD refs/heads/main
git -C "$test_root/seed" switch --quiet -c develop
echo feature >> "$test_root/seed/app.txt"
git -C "$test_root/seed" commit --quiet -am "feat: add test feature"
git -C "$test_root/seed" push --quiet -u origin develop

git clone --quiet "$test_root/origin.git" "$test_root/work"
git -C "$test_root/work" config user.name "Promotion Bot"
git -C "$test_root/work" config user.email "promotion-bot@example.com"
git -C "$test_root/work" fetch --quiet origin main develop

first_output="$test_root/first-output"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$first_output" "$subject"
)
grep -qx 'promotion_required=true' "$first_output"
assert_ancestor "$test_root/work" origin/main automation/promote-develop
assert_ancestor "$test_root/work" origin/develop automation/promote-develop

git -C "$test_root/seed" switch --quiet main
echo release > "$test_root/seed/VERSION"
git -C "$test_root/seed" add VERSION
git -C "$test_root/seed" commit --quiet -m "chore(main): release 1.1.0"
git -C "$test_root/seed" push --quiet origin main
git -C "$test_root/work" fetch --quiet origin main develop

second_output="$test_root/second-output"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$second_output" "$subject"
)
grep -qx 'promotion_required=true' "$second_output"
assert_ancestor "$test_root/work" origin/main automation/promote-develop
assert_ancestor "$test_root/work" origin/develop automation/promote-develop

git -C "$test_root/seed" merge --quiet --no-ff develop -m "chore: promote develop to main"
git -C "$test_root/seed" push --quiet origin main
git -C "$test_root/work" fetch --quiet origin main develop

third_output="$test_root/third-output"
before_sha="$(git -C "$test_root/work" rev-parse automation/promote-develop)"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$third_output" "$subject"
)
grep -qx 'promotion_required=false' "$third_output"
test "$before_sha" = "$(git -C "$test_root/work" rev-parse automation/promote-develop)"

echo "Promotion branch regression tests passed."
