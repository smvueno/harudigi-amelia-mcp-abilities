#!/usr/bin/env bash
# Build installable production ZIPs.
#   ./scripts/build-zip.sh          → GitHub distribution (with updater)
#   ./scripts/build-zip.sh wporg    → WordPress.org distribution (no GitHub updater)
#
# Strategy: copy plugin root, exclude non-production paths (denylist).
# New files under includes/ ship automatically. Dev/docs/CI never ship.
# Install slug: harudigi-booking-abilities-for-amelia
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MODE="${1:-github}"
MAIN="harudigi-booking-abilities-for-amelia.php"
SLUG="harudigi-booking-abilities-for-amelia"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"

VERSION="$(
  grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$ROOT/$MAIN" | head -1 \
    | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'
)"
if [[ -z "$VERSION" || ! "$VERSION" =~ ^[0-9] ]]; then
  echo "Could not read a valid Version from $MAIN (got: '${VERSION:-empty}')" >&2
  exit 1
fi

OUT_NAME="${SLUG}-${VERSION}"
[[ "$MODE" == "wporg" ]] && OUT_NAME="${OUT_NAME}-wporg"
OUT_ZIP="$DIST/${OUT_NAME}.zip"

rm -rf "$STAGE"
mkdir -p "$STAGE"

# Denylist: anything not needed to run the plugin on a customer site.
# Prefer excludes over a brittle include allowlist so new includes/*.php ship.
rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.wordpress-org/' \
  --exclude 'docs/' \
  --exclude 'dist/' \
  --exclude 'scripts/' \
  --exclude 'tests/' \
  --exclude 'bin/' \
  --exclude 'node_modules/' \
  --exclude 'vendor/' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude '.editorconfig' \
  --exclude '.phpcs.xml*' \
  --exclude 'phpunit.xml*' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'README.md' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'SECURITY.md' \
  --exclude 'CHANGELOG.md' \
  --exclude '.DS_Store' \
  --exclude '*.log' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude '*credentials*' \
  --exclude '*secret*' \
  --exclude 'tasks*.json' \
  --exclude 'tasks*.md' \
  --exclude 'PRD.md' \
  "$ROOT"/ "$STAGE"/

if [[ "$MODE" == "wporg" ]]; then
  # Drop GitHub updater file(s) by name pattern — no hardcoded single path.
  find "$STAGE" -type f \( -iname '*github*updater*.php' -o -iname '*github-updater*.php' \) -delete

  python3 - "$STAGE/$MAIN" <<'PY'
"""Soft-strip GitHub update wiring for WordPress.org builds.

Pattern-based (not pinned to exact source formatting): drop Update URI,
any statement/block mentioning github-updater / GitHub_Updater / *_mcp_updater.
"""
import re
import sys
from pathlib import Path

NEEDLE = re.compile(
    r"(?i)Update URI:|github[-_]?updater|GitHub_Updater|_mcp_updater"
)

def find_matching(text: str, open_idx: int, open_ch: str, close_ch: str) -> int:
    depth = 0
    i = open_idx
    while i < len(text):
        ch = text[i]
        if ch == open_ch:
            depth += 1
        elif ch == close_ch:
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1

def remove_brace_blocks_touching_needle(text: str) -> str:
    """Remove `if (...) { ... }` blocks whose header or body matches NEEDLE."""
    out = []
    i = 0
    if_re = re.compile(r"(?m)^[ \t]*if\s*\(")
    while True:
        m = if_re.search(text, i)
        if not m:
            out.append(text[i:])
            break
        out.append(text[i:m.start()])
        cond_open = m.end() - 1  # '('
        cond_close = find_matching(text, cond_open, "(", ")")
        if cond_close < 0:
            out.append(text[m.start():])
            break
        j = cond_close + 1
        while j < len(text) and text[j] in " \t":
            j += 1
        if j >= len(text) or text[j] != "{":
            # not a brace if — keep and continue
            out.append(text[m.start():cond_close + 1])
            i = cond_close + 1
            continue
        body_close = find_matching(text, j, "{", "}")
        if body_close < 0:
            out.append(text[m.start():])
            break
        k = body_close + 1
        if k < len(text) and text[k] == "\n":
            k += 1
        block = text[m.start():k]
        if NEEDLE.search(block):
            i = k  # drop block
        else:
            out.append(block)
            i = k
    return "".join(out)

p = Path(sys.argv[1])
text = p.read_text(encoding="utf-8")
orig = text

# 1) Drop whole if-blocks that mention updater (require wrapper + init wrapper).
text = remove_brace_blocks_touching_needle(text)

# 2) Drop remaining single lines (assignment, Update URI, bare require).
kept = []
for ln in text.splitlines(keepends=True):
    if NEEDLE.search(ln):
        continue
    kept.append(ln)
text = "".join(kept)

# Collapse 3+ blank lines.
text = re.sub(r"\n{3,}", "\n\n", text)

if text == orig:
    print("wporg strip: no updater wiring found (already clean)")
else:
    p.write_text(text, encoding="utf-8")
    print("wporg strip: removed Update URI / updater require / init")

body = p.read_text(encoding="utf-8")
leftovers = []
if re.search(r"(?i)Update URI:", body):
    leftovers.append("Update URI header")
if re.search(r"(?i)github[-_]?updater", body):
    leftovers.append("github-updater reference")
if re.search(r"GitHub_Updater", body):
    leftovers.append("GitHub_Updater reference")
if leftovers:
    raise SystemExit("wporg strip incomplete: " + ", ".join(leftovers))
PY
fi

rm -f "$OUT_ZIP"
(
  cd "$DIST"
  zip -rq "$(basename "$OUT_ZIP")" "$SLUG" -x '*.DS_Store' -x '*/.DS_Store'
)

echo "Built: $OUT_ZIP"
ls -la "$OUT_ZIP"

# --- Production audit (fail closed) ---
python3 - "$OUT_ZIP" "$MODE" "$SLUG" <<'PY'
import re
import sys
import zipfile

zip_path, mode, slug = sys.argv[1], sys.argv[2], sys.argv[3]
deny_name = re.compile(
    r"(?i)(^|/)"
    r"("
    r"\.git|\.github|\.env|credentials|secret|"
    r"local-test|pentest|phpunit|composer\.(json|lock)|"
    r"package(-lock)?\.json|node_modules|vendor|"
    r"CONTRIBUTING|SECURITY|README\.md|PRD\.md|tasks"
    r")"
)
deny_path = re.compile(r"(?i)/(scripts|tests|docs|dist|bin|\.wordpress-org)/")
errors = []
names = []

with zipfile.ZipFile(zip_path) as zf:
    names = zf.namelist()
    if not any(n == f"{slug}/" or n.startswith(f"{slug}/") for n in names):
        errors.append(f"zip root must be folder '{slug}/'")
    for n in names:
        if n.endswith("/"):
            continue
        base = n.split("/")[-1]
        if deny_path.search("/" + n) or deny_name.search(n):
            errors.append(f"non-production path: {n}")
        if base.endswith((".log", ".sql", ".pem", ".key")):
            errors.append(f"sensitive extension: {n}")
        if mode == "wporg" and re.search(r"(?i)github.*updater", n):
            errors.append(f"wporg zip still has updater: {n}")
    # Must contain main plugin + readme.txt
    main = f"{slug}/harudigi-booking-abilities-for-amelia.php"
    readme = f"{slug}/readme.txt"
    if main not in names:
        errors.append(f"missing {main}")
    if readme not in names:
        errors.append(f"missing {readme}")
    if mode == "github":
        # Updater should be present for GitHub builds.
        if not any(re.search(r"(?i)github.*updater.*\.php$", n) for n in names):
            errors.append("github zip missing updater PHP")
    # Scan main for wporg leftovers
    if mode == "wporg" and main in names:
        body = zf.read(main).decode("utf-8", errors="replace")
        if re.search(r"(?i)Update URI:", body):
            errors.append("wporg main still has Update URI")
        if "GitHub_Updater" in body or re.search(r"(?i)github[-_]?updater", body):
            errors.append("wporg main still references GitHub updater")

print(f"Audit {mode}: {len([n for n in names if not n.endswith('/')])} files")
for n in sorted(n for n in names if not n.endswith("/")):
    print(f"  {n}")
if errors:
    print("AUDIT FAILED:", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)
print("AUDIT OK")
PY
