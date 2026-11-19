#!/usr/bin/env bash
#
# Build dist/buddypress-group-moderation-<version>.zip from the tree, excluding .distignore entries.
#
# Usage: bash bin/build-release.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
fail() { printf '\033[31mFAIL: %s\033[0m\n' "$*"; exit 1; }

SLUG="buddypress-group-moderation"
HDR="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$SLUG.php" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')"
CONST="$(grep -m1 "define( 'BP_GROUP_MODERATION_VERSION'" "$SLUG.php" | sed -E "s/.*'([0-9][^']*)'.*/\1/")"
TAG="$(grep -m1 '^Stable tag:' readme.txt | sed -E 's/Stable tag:[[:space:]]*//')"
[ "$HDR" = "$CONST" ] && [ "$HDR" = "$TAG" ] || fail "version mismatch: header=$HDR const=$CONST readme=$TAG"

bash bin/local-ci.sh

STAGE="dist/_stage/$SLUG"
rm -rf dist/_stage "dist/$SLUG-$HDR.zip"
mkdir -p "$STAGE"
rsync -a --exclude-from=.distignore ./ "$STAGE/"
(cd dist/_stage && zip -qr -9 "../$SLUG-$HDR.zip" "$SLUG")
rm -rf dist/_stage

LEAKS="$(unzip -Z1 "dist/$SLUG-$HDR.zip" | grep -E '(^|/)(audit|bin|docs|node_modules)/|\.md$|\.map$|gruntfile|package(-lock)?\.json' || true)"
[ -z "$LEAKS" ] || fail "dev files in zip: $LEAKS"
echo "dist/$SLUG-$HDR.zip"
