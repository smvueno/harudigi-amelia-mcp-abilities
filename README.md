# MCP Abilities for Amelia – HaruDigi

![MCP Abilities for Amelia – HaruDigi](docs/banner.png)

**Give Easy MCP AI the missing Amelia admin surface on WordPress.**  
This plugin adds about 90 Amelia abilities for catalog, bookings, payments, settings, and maintenance while keeping Amelia's native abilities intact.

Built by [HaruDigi](https://harudigi.com) — websites, reach, automations, and trust for SMBs.

> **Independent plugin.** MCP Abilities for Amelia is built by HaruDigi and is **not affiliated with or endorsed by TMS Software** (Amelia Booking).

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![GitHub release](https://img.shields.io/github/v/release/smvueno/harudigi-amelia-mcp-abilities)](https://github.com/smvueno/harudigi-amelia-mcp-abilities/releases)

**Docs:** [smvueno.github.io/harudigi-amelia-mcp-abilities](https://smvueno.github.io/harudigi-amelia-mcp-abilities/)  
**Author:** Jens Madsen · **Brand:** HaruDigi · **Site:** [harudigi.com](https://harudigi.com)  
**Install slug:** `mcp-abilities-for-amelia`

---

## What it does

- Adds the missing Amelia admin MCP surface for Easy MCP AI
- Covers services, categories, locations, employees, appointments, events, customers, resources, coupons, custom fields, payments, and more
- Keeps Amelia core abilities intact instead of duplicating them
- Redacts secrets and blocks unsafe fields where needed
- Requires confirmation for destructive deletes
- Ships two distribution modes:
  - GitHub ZIP with updater
  - WordPress.org ZIP without GitHub updater

## Requirements

| Requirement | Notes |
|-------------|--------|
| WordPress 6.9+ | |
| PHP 7.4+ | |
| Easy MCP AI | **Required** |
| Amelia Booking **9.7+** | Required for abilities to run |

## Install

1. Activate **Easy MCP AI** and **Amelia Booking 9.7+**.
2. Download a release ZIP:
   - **GitHub updates:** `mcp-abilities-for-amelia-x.y.z.zip`
   - **WordPress.org submission:** `mcp-abilities-for-amelia-x.y.z-wporg.zip` (no GitHub updater)
3. Upload / activate **MCP Abilities for Amelia – HaruDigi**.

### Migrating from older folders

If you still have `amelia-mcp-abilities` or `harudigi-amelia-mcp-abilities`, deactivate and delete that folder, then install `mcp-abilities-for-amelia`. Ability slugs (`amelia/*`) are unchanged.

## Updates

| How you installed | Updates from |
|-------------------|--------------|
| GitHub / direct ZIP | Public GitHub Releases (Update URI + updater in that ZIP) |
| WordPress.org | wordpress.org only (wporg ZIP has no GitHub updater) |

## Repository assets

- GitHub banner preview: `docs/banner.png`
- GitHub icon: `docs/icon.png`
- WordPress.org banner + icon set: `.wordpress-org/`

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Links

- [HaruDigi](https://harudigi.com)
- [Docs](https://smvueno.github.io/harudigi-amelia-mcp-abilities/)
- [Issues](https://github.com/smvueno/harudigi-amelia-mcp-abilities/issues)
- [Security](SECURITY.md)
