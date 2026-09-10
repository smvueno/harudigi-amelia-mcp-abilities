---
name: harudigi-amelia-mcp
description: >-
  Maintain HaruDigi Booking Abilities for Amelia (meta MCP plugin). Use when
  changing this repo, releasing, changelogs, readme, SVN/WordPress.org, GitHub
  Actions, zips, or Amelia/Easy MCP abilities.
---

# HaruDigi Amelia MCP — maintain

Repo: `harudigi-booking-abilities-for-amelia` (GitHub `smvueno/harudigi-amelia-mcp-abilities`).  
Slug: `harudigi-booking-abilities-for-amelia`. Meta tools only (≤7).

## Changelog (required)

**SSOT = `CHANGELOG.md`.** Never hand-edit Changelog / Upgrade Notice in `readme.txt`.

Before any version bump or tag:

1. Add `## [X.Y.Z] - YYYY-MM-DD` with human bullets (`### Added|Changed|Fixed|…`).
2. Optional `### Upgrade notice` (one short line for wp.org).
3. Bump `Version` + `HARUDIGI_AMELIA_MCP_VERSION` in main PHP; `Stable tag` is set by sync.
4. Run: `python3 scripts/changelog.py check`

```bash
python3 scripts/changelog.py sync              # CHANGELOG → readme.txt
python3 scripts/changelog.py check             # fail if Version missing notes
python3 scripts/changelog.py github-body X.Y.Z # preview GitHub release body
```

CI: `.github/workflows/changelog.yml` fails PRs if notes missing or readme drifted.

## Release

```bash
# after check passes
git tag -a vX.Y.Z -m "vX.Y.Z"
git push origin main vX.Y.Z
```

Tag `v*` runs Release: sync notes → zips → GitHub release body from CHANGELOG → SVN deploy.

Secrets: `SVN_USERNAME`, `SVN_PASSWORD` (wp.org SVN password).  
Do **not** put passwords in the repo; local only `~/.config/harudigi/wporg-svn.env`.

## Do

- Keep exactly the 7 meta abilities; rich tool descriptions; `confirm:true` on destroy; `notify` default false.
- Status omitted → Amelia `defaultAppointmentStatus`.
- Leave Amelia’s `/wp-json/mcp/*` adapter alone (only unregister natives in Abilities API).
- Production zips via `scripts/build-zip.sh` (github | wporg). wporg strips GitHub updater.
- Prefer `./scripts/build-zip.sh` denylist / `.distignore` over hardcoded file lists.

## Do not

- Re-add fine-grained `amelia/*` ability spam.
- Commit `.env`, SVN passwords, `scripts/pentest*`, or tokens.
- Put main plugin PHP under a nested folder in SVN trunk.
- Hand-write per-release notes in `release.yml`.
- Patch Amelia core / vendored mcp-adapter for log noise.
- Force-push `main` or rewrite published tags without explicit human ask.

## Surfaces

| Audience | Source |
|----------|--------|
| GitHub Release | `CHANGELOG.md` section + `.github/release-footer.md` |
| wordpress.org | `readme.txt` (generated) |
| Humans in git | `CHANGELOG.md` |

## Quick refs

- Easy MCP endpoint: `/wp-json/easy-mcp-ai/v1/mcp`
- SVN: `https://plugins.svn.wordpress.org/harudigi-booking-abilities-for-amelia/`
- Min Amelia: 9.7 · Requires Easy MCP AI · Trademark-safe name (never start with “Amelia”)
