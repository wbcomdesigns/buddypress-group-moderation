#!/usr/bin/env bash
#
# Local CI for buddypress-group-moderation: PHP lint, WPCS on files changed
# against origin, the card acceptance greps, the built-in licence key check and
# the release gate check.
#
# Usage: bash bin/local-ci.sh            (an empty licence key is a warning)
#        BGM_RELEASE=1 bash bin/local-ci.sh (an empty licence key fails; set by bin/build-release.sh)
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

step 4 "Built-in free licence key"
key="$(grep -m1 "define( 'EDD_GROUP_MODERATION_FREE_LICENSE_KEY'" edd-license/edd-plugin-license.php | sed -E "s/.*'EDD_GROUP_MODERATION_FREE_LICENSE_KEY', '([^']*)'.*/\1/")"
if [ -z "$key" ]; then
	msg="EDD_GROUP_MODERATION_FREE_LICENSE_KEY is empty in edd-license/edd-plugin-license.php: set it to the free blanket licence key from the EDD store, or the release gets no updates"
	[ "${BGM_RELEASE:-0}" = "1" ] && fail "$msg"
	echo "    WARNING: $msg"
fi

step 5 "Release gates"
gate="$HOME/.claude/skills/wp-plugin-development/scripts/gate-check.sh"
if [ -f "$gate" ]; then bash "$gate" .; else echo "    gate-check.sh not found: skipped"; fi
