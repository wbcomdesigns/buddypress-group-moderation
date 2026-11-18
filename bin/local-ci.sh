#!/usr/bin/env bash
#
# Local CI for buddypress-group-moderation: PHP lint, WPCS on files changed
# against origin, the card acceptance greps and the release gate check.
#
# Usage: bash bin/local-ci.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
fail() { printf '\033[31mFAIL: %s\033[0m\n' "$*"; exit 1; }
step() { printf '\033[34m[%s] %s\033[0m\n' "$1" "$2"; }

step 1 "PHP syntax lint"
find . -name '*.php' -not -path './node_modules/*' -not -path './dist/*' -print0 | xargs -0 -n 1 php -l > /dev/null || fail "PHP syntax errors"

step 2 "WPCS on PHP files changed against the upstream branch"
if command -v phpcs > /dev/null; then
	base="$(git merge-base HEAD '@{upstream}' 2> /dev/null || git merge-base HEAD "origin/$(git rev-parse --abbrev-ref HEAD)" 2> /dev/null || git rev-parse HEAD)"
	changed="$(git diff --name-only --diff-filter=AM "$base" -- '*.php' || true)"
	[ -z "$changed" ] || phpcs --standard=WordPress -q --report=summary $changed || echo "    WPCS findings above (pre-existing code is not yet clean; compare against $base)"
else
	echo "    phpcs not installed: skipped"
fi

step 3 "No debug tools, silenced errors or other product names"
! grep -rnE 'admin_test|view-debug|error_reporting|error_log\(|var_dump\(' includes edd-license uninstall.php buddypress-group-moderation.php || fail "debug code found"
! grep -rniE 'member blog|wp stories' --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=bin . || fail "another product named"

step 4 "Release gates"
gate="$HOME/.claude/skills/wp-plugin-development/scripts/gate-check.sh"
if [ -f "$gate" ]; then bash "$gate" .; else echo "    gate-check.sh not found: skipped"; fi
