#!/usr/bin/env bash
# Build installable ZIPs.
#   ./scripts/build-zip.sh          → GitHub distribution (with updater)
#   ./scripts/build-zip.sh wporg    → WordPress.org distribution (no updater / Update URI)
#
# Install slug (folder inside ZIP): mcp-abilities-for-amelia
# GitHub repo may remain smvueno/harudigi-amelia-mcp-abilities.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MODE="${1:-github}"
MAIN="mcp-abilities-for-amelia.php"
VERSION="$(grep -E "^\s*\*\s*Version:" "$ROOT/$MAIN" | head -1 | sed -E 's/.*Version:\s*//')"
SLUG="mcp-abilities-for-amelia"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"
OUT_NAME="${SLUG}-${VERSION}"
[[ "$MODE" == "wporg" ]] && OUT_NAME="${OUT_NAME}-wporg"
OUT_ZIP="$DIST/${OUT_NAME}.zip"

rm -rf "$STAGE"
mkdir -p "$STAGE"

rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.wordpress-org' \
  --exclude 'docs' \
  --exclude 'dist' \
  --exclude 'scripts' \
  --exclude '.gitignore' \
  --exclude 'README.md' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'SECURITY.md' \
  --exclude '.DS_Store' \
  "$ROOT"/ "$STAGE"/

if [[ "$MODE" == "wporg" ]]; then
  rm -f "$STAGE/includes/class-github-updater.php"
  sed -i '/^\s*\* Update URI:/d' "$STAGE/$MAIN"
  python3 - "$STAGE/$MAIN" <<'PY'
import sys
from pathlib import Path
p = Path(sys.argv[1])
text = p.read_text()
start = text.find("// GitHub distribution only")
end = text.find("/**\n * Abilities shipped by Amelia core")
if start == -1 or end == -1:
    raise SystemExit("could not strip updater boot block")
p.write_text(text[:start] + text[end:])
print("stripped updater boot block")
PY
fi

rm -f "$OUT_ZIP"
(
  cd "$DIST"
  zip -r "$(basename "$OUT_ZIP")" "$SLUG" -x '*.DS_Store'
)

echo "Built: $OUT_ZIP"
ls -la "$OUT_ZIP"
