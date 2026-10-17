#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
: "${GH_TOKEN:?GH_TOKEN is required}"
[[ "$GITHUB_REPOSITORY" == zdossantos/dlp-friends ]] || exit 1
[[ "$GH_TOKEN" != *$'\n'* && "$GH_TOKEN" != *'"'* && "$GH_TOKEN" != *'\'* ]] || exit 1
api_url="${GITHUB_API_URL:-https://api.github.com}"
[[ "$api_url" == https://api.github.com || "$api_url" =~ ^http://127\.0\.0\.1:[0-9]+$ ]] || exit 1
directory="$(mktemp -d)"
trap 'rm -rf "$directory"' EXIT
get() {
    printf 'header = "Authorization: Bearer %s"\n' "$GH_TOKEN" |
        curl --config - --fail --silent --show-error --connect-timeout 10 --max-time 60 \
            --header 'Accept: application/vnd.github+json' --header 'X-GitHub-Api-Version: 2022-11-28' \
            "$api_url/repos/$GITHUB_REPOSITORY/$1" > "$2"
}
get 'pulls?state=open&base=main&per_page=100' "$directory/prs.json"
jq '[.[] | select(.state == "open" and .draft == false and .base.ref == "main"
    and .base.repo.full_name == "zdossantos/dlp-friends" and .head.repo.full_name == "zdossantos/dlp-friends"
    and .user.login == "zdossantos" and .head.ref == "release-please--branches--main--components--dlp-friends"
    and any(.labels[]; .name == "autorelease: pending"))]' "$directory/prs.json" > "$directory/eligible.json"
count="$(jq 'length' "$directory/eligible.json")"
if [[ "$count" == 0 ]]; then echo null; exit 0; fi
[[ "$count" == 1 ]] || exit 1
sha="$(jq -r '.[0].head.sha' "$directory/eligible.json")"
[[ "$sha" =~ ^[a-f0-9]{40}$ ]] || exit 1
get "commits/$sha/check-runs?per_page=100&filter=latest" "$directory/checks.json"
if ! jq -e --arg sha "$sha" '
    .total_count <= 100 and
    (["Conventional PR title", "PHP quality", "Backend tests", "Frontend quality", "Vite build", "Docker build"] |
    all(. as $name | $checks[0].check_runs | map(select(.name == $name and .app.slug == "github-actions")) |
        sort_by(.id) | last | .head_sha == $sha and .status == "completed" and .conclusion == "success"))
    ' --slurpfile checks "$directory/checks.json" "$directory/checks.json" >/dev/null; then
    echo null
    exit 0
fi
get "git/commits/$sha" "$directory/commit.json"
jq -e --arg sha "$sha" '.sha == $sha and (.tree.sha | test("^[a-f0-9]{40}$"))' "$directory/commit.json" >/dev/null
jq --arg tree "$(jq -r .tree.sha "$directory/commit.json")" '.[0] | {number, sha: .head.sha, tree: $tree, head_ref: .head.ref}' "$directory/eligible.json"
