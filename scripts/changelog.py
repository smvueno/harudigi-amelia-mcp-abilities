#!/usr/bin/env python3
"""CHANGELOG.md → readme.txt + GitHub release body.

Commands:
  sync              Rewrite readme Changelog + Upgrade Notice from CHANGELOG.md
  check [--version] Fail if plugin Version lacks a CHANGELOG section (and optionally mismatch tag)
  github-body VER   Print human release notes for GitHub (stdout)
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CHANGELOG = ROOT / "CHANGELOG.md"
README = ROOT / "readme.txt"
MAIN = ROOT / "harudigi-booking-abilities-for-amelia.php"
FOOTER = ROOT / ".github" / "release-footer.md"

SECTION_RE = re.compile(
    r"^## \[(\d+\.\d+\.\d+[^\]]*)\](?:\s*-\s*(\d{4}-\d{2}-\d{2}))?\s*$",
    re.M,
)
H3_RE = re.compile(r"^###\s+(.+)\s*$", re.M)


def parse_changelog(text: str) -> list[dict]:
    matches = list(SECTION_RE.finditer(text))
    out: list[dict] = []
    for i, m in enumerate(matches):
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        body = text[start:end].strip()
        upgrade = ""
        bullets: list[str] = []
        current_h3 = ""
        for line in body.splitlines():
            h3 = H3_RE.match(line)
            if h3:
                current_h3 = h3.group(1).strip().lower()
                continue
            if current_h3 == "upgrade notice":
                if line.strip() and not line.strip().startswith("#"):
                    upgrade = (upgrade + " " + line.strip()).strip()
                continue
            if line.startswith("- ") or line.startswith("* "):
                item = line[2:].strip()
                # Drop markdown bold markers for wp.org plainness
                item = re.sub(r"\*\*(.+?)\*\*", r"\1", item)
                prefix = ""
                if current_h3 in ("added", "changed", "fixed", "removed", "deprecated", "security"):
                    prefix = current_h3.capitalize() + ": "
                bullets.append(prefix + item)
        out.append(
            {
                "version": m.group(1),
                "date": m.group(2) or "",
                "bullets": bullets,
                "upgrade": upgrade,
                "raw_md": body,
            }
        )
    return out


def plugin_version() -> str:
    text = MAIN.read_text(encoding="utf-8")
    m = re.search(r"^\s*\*\s*Version:\s*(\S+)", text, re.M)
    if not m:
        raise SystemExit("Could not read Version from main plugin file")
    return m.group(1).strip()


def section_md_for_github(entry: dict) -> str:
    """Human-readable markdown notes (skip Upgrade notice h3; keep other h3)."""
    lines: list[str] = []
    skip = False
    for line in entry["raw_md"].splitlines():
        h3 = H3_RE.match(line)
        if h3:
            skip = h3.group(1).strip().lower() == "upgrade notice"
            if skip:
                continue
            lines.append(line)
            continue
        if skip:
            continue
        lines.append(line)
    return "\n".join(lines).strip()


def sync_readme(entries: list[dict]) -> None:
    text = README.read_text(encoding="utf-8")
    # Keep last ~15 versions in readme to avoid huge files
    keep = entries[:15]
    changelog_lines = ["== Changelog ==", ""]
    upgrade_lines = ["== Upgrade Notice ==", ""]
    for e in keep:
        changelog_lines.append(f"= {e['version']} =")
        for b in e["bullets"]:
            changelog_lines.append(f"* {b}")
        if not e["bullets"]:
            changelog_lines.append("* See CHANGELOG.md")
        changelog_lines.append("")
        notice = e["upgrade"] or (e["bullets"][0] if e["bullets"] else f"Version {e['version']}")
        upgrade_lines.append(f"= {e['version']} =")
        upgrade_lines.append(notice)
        upgrade_lines.append("")

    new_tail = "\n".join(changelog_lines).rstrip() + "\n\n" + "\n".join(upgrade_lines).rstrip() + "\n"
    # Replace from == Changelog == to EOF
    if "== Changelog ==" not in text:
        raise SystemExit("readme.txt missing == Changelog ==")
    head = text.split("== Changelog ==", 1)[0].rstrip() + "\n\n"
    # Sync Stable tag to newest changelog / plugin version
    ver = plugin_version()
    head = re.sub(r"(?m)^(Stable tag:\s*).*$", rf"\g<1>{ver}", head)
    README.write_text(head + new_tail, encoding="utf-8")
    print(f"Synced readme.txt changelog ({len(keep)} versions); Stable tag={ver}")


def cmd_sync(_: argparse.Namespace) -> None:
    entries = parse_changelog(CHANGELOG.read_text(encoding="utf-8"))
    if not entries:
        raise SystemExit("CHANGELOG.md has no ## [x.y.z] sections")
    sync_readme(entries)


def cmd_check(args: argparse.Namespace) -> None:
    entries = parse_changelog(CHANGELOG.read_text(encoding="utf-8"))
    versions = {e["version"] for e in entries}
    ver = args.version or plugin_version()
    if ver not in versions:
        raise SystemExit(
            f"CHANGELOG.md missing ## [{ver}] — add release notes before tagging/shipping"
        )
    entry = next(e for e in entries if e["version"] == ver)
    if not entry["bullets"] and not section_md_for_github(entry):
        raise SystemExit(f"CHANGELOG section [{ver}] has no notes")
    # Ensure readme matches after sync would
    sync_readme(entries)
    print(f"OK: CHANGELOG has [{ver}] with {len(entry['bullets'])} bullets; readme synced")


def cmd_github_body(args: argparse.Namespace) -> None:
    ver = args.version
    entries = parse_changelog(CHANGELOG.read_text(encoding="utf-8"))
    entry = next((e for e in entries if e["version"] == ver), None)
    if not entry:
        raise SystemExit(f"CHANGELOG.md missing ## [{ver}]")
    notes = section_md_for_github(entry)
    footer = FOOTER.read_text(encoding="utf-8") if FOOTER.exists() else ""
    zip_name = f"harudigi-booking-abilities-for-amelia-{ver}.zip"
    zip_wporg = f"harudigi-booking-abilities-for-amelia-{ver}-wporg.zip"
    body = (
        footer.replace("{{VERSION}}", ver)
        .replace("{{NOTES}}", notes)
        .replace("{{ZIP}}", zip_name)
        .replace("{{ZIP_WPORG}}", zip_wporg)
    )
    sys.stdout.write(body.rstrip() + "\n")


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("sync", help="Write CHANGELOG into readme.txt")
    s.set_defaults(func=cmd_sync)

    c = sub.add_parser("check", help="Require notes for plugin/tag version; sync readme")
    c.add_argument("--version", help="Version to require (default: plugin header)")
    c.set_defaults(func=cmd_check)

    g = sub.add_parser("github-body", help="Print GitHub release body for a version")
    g.add_argument("version")
    g.set_defaults(func=cmd_github_body)

    args = p.parse_args()
    args.func(args)


if __name__ == "__main__":
    main()
