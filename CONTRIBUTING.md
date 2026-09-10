# Contributing

Thanks for helping improve **HaruDigi Booking Abilities for Amelia and Easy MCP AI**.

Independent HaruDigi plugin — **not affiliated with or endorsed by TMS Software**.

## Agent / maintainer skill

Follow **[`.cursor/skills/harudigi-amelia-mcp/SKILL.md`](.cursor/skills/harudigi-amelia-mcp/SKILL.md)** for releases, changelog, SVN, and watchouts.

## Changelog

**Edit `CHANGELOG.md` only.** Then:

```bash
python3 scripts/changelog.py check   # syncs readme.txt; fails if Version has no notes
```

## Release

1. Notes in `CHANGELOG.md` for `X.Y.Z`
2. Bump plugin `Version` to `X.Y.Z`
3. `python3 scripts/changelog.py check`
4. Tag `vX.Y.Z` and push — CI ships GitHub + wordpress.org

Secrets: `SVN_USERNAME`, `SVN_PASSWORD`.

## Builds

```bash
./scripts/build-zip.sh        # GitHub ZIP (with updater)
./scripts/build-zip.sh wporg  # WordPress.org ZIP (no updater)
```

— Jens Madsen · HaruDigi · https://harudigi.com
