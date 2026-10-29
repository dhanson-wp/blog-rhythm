#!/usr/bin/env bash
# Build the production zip: blog-rhythm.zip with a blog-rhythm/ root folder.
# Usage: npm run package  (builds assets first, then zips).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="blog-rhythm"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

cd "$ROOT"

if [ ! -f build/widget.js ] || [ ! -f build/settings.js ]; then
	echo "build/ is missing. Run npm run build first." >&2
	exit 1
fi

mkdir -p "$STAGE/$SLUG"
rsync -a --exclude-from=.distignore ./ "$STAGE/$SLUG/"

# Guard: only the expected top-level entries may ship.
EXPECTED="LICENSE blog-rhythm.php build includes languages readme.txt uninstall.php"
ACTUAL="$(cd "$STAGE/$SLUG" && ls -A | sort | tr '\n' ' ' | sed 's/ $//')"
if [ "$ACTUAL" != "$EXPECTED" ]; then
	echo "Unexpected zip contents." >&2
	echo "  expected: $EXPECTED" >&2
	echo "  actual:   $ACTUAL" >&2
	exit 1
fi

rm -f "$ROOT/$SLUG.zip"
(cd "$STAGE" && zip -qr "$ROOT/$SLUG.zip" "$SLUG")
echo "Created $SLUG.zip"
