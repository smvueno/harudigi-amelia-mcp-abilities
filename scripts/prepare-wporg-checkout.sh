#!/usr/bin/env bash
# Prepare the current checkout for a WordPress.org SVN deploy (CI or local).
# Removes GitHub updater file + soft-strips Update URI / require / init from main.
# Does NOT touch secrets. Safe to run on a disposable Actions runner.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MAIN="$ROOT/harudigi-booking-abilities-for-amelia.php"

find "$ROOT" -type f \( -iname '*github*updater*.php' -o -iname '*github-updater*.php' \) -delete

python3 - "$MAIN" <<'PY'
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
    out = []
    i = 0
    if_re = re.compile(r"(?m)^[ \t]*if\s*\(")
    while True:
        m = if_re.search(text, i)
        if not m:
            out.append(text[i:])
            break
        out.append(text[i:m.start()])
        cond_open = m.end() - 1
        cond_close = find_matching(text, cond_open, "(", ")")
        if cond_close < 0:
            out.append(text[m.start():])
            break
        j = cond_close + 1
        while j < len(text) and text[j] in " \t":
            j += 1
        if j >= len(text) or text[j] != "{":
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
            i = k
        else:
            out.append(block)
            i = k
    return "".join(out)

p = Path(sys.argv[1])
text = p.read_text(encoding="utf-8")
text = remove_brace_blocks_touching_needle(text)
kept = [ln for ln in text.splitlines(keepends=True) if not NEEDLE.search(ln)]
text = re.sub(r"\n{3,}", "\n\n", "".join(kept))
p.write_text(text, encoding="utf-8")
body = p.read_text(encoding="utf-8")
bad = []
if re.search(r"(?i)Update URI:", body):
    bad.append("Update URI")
if re.search(r"(?i)github[-_]?updater", body) or "GitHub_Updater" in body:
    bad.append("updater refs")
if bad:
    raise SystemExit("prepare-wporg incomplete: " + ", ".join(bad))
print("prepare-wporg-checkout: stripped updater wiring")
PY
