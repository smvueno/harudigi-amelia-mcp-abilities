# Haru Digi Amelia MCP Abilities

![Haru Digi Amelia MCP Abilities](docs/banner.png)

**Give AI agents full, safe control of Amelia Booking on WordPress.**  
Built by [Haru Digi](https://harudigi.com) — websites, reach, automations, and trust for SMBs.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![GitHub release](https://img.shields.io/github/v/release/smvueno/harudigi-amelia-mcp-abilities)](https://github.com/smvueno/harudigi-amelia-mcp-abilities/releases)

**Docs:** [smvueno.github.io/harudigi-amelia-mcp-abilities](https://smvueno.github.io/harudigi-amelia-mcp-abilities/)  
**Author:** Jens Madsen · **Brand:** Haru Digi · **Site:** [harudigi.com](https://harudigi.com)

---

## Why Haru Digi ships this

Haru Digi helps small and mid-sized businesses get a **proper website**, **stronger reach to clients**, **practical automations**, and **trust** — with people and with AI engines.

- **Haru** — Japanese for a new beginning and spring  
- **Digi** — digital  

This plugin is part of that mission: make WordPress booking ops **AI-ready** without exposing secrets or unsafe deletes.

## What it does

Amelia Pro already registers a core set of MCP abilities. This plugin **fills the gaps** for catalog, bookings, payments, and settings:

- Extra reads/writes for services, categories, locations, employees, packages, extras, resources, coupons, custom fields
- Appointment/event/customer ops beyond the native set
- Payment bookkeeping + remaining-balance payment links
- Hardening: redacted secrets, blocked password/externalId writes, `confirm=true` for destructive deletes

Ability slugs stay under `amelia/*` so existing MCP clients keep working.

## Requirements

| Requirement | Notes |
|-------------|--------|
| WordPress 6.9+ | Abilities API era |
| PHP 7.4+ | |
| Amelia Booking | Active (`Requires Plugins: ameliabooking`) |
| Easy MCP AI | Recommended bridge for Cursor / MCP hosts |

## Install

1. Download the latest **`harudigi-amelia-mcp-abilities-*.zip`** from [Releases](https://github.com/smvueno/harudigi-amelia-mcp-abilities/releases).
2. In WordPress: **Plugins → Add New → Upload Plugin**.
3. Activate **Haru Digi Amelia MCP Abilities**.
4. Optional: **Enable auto-updates** on the plugin row (checks public GitHub Releases).

### Migrating from `amelia-mcp-abilities`

Deactivate and delete the old folder, then install this plugin. Ability slugs are unchanged; only the plugin folder/slug and branding changed.

## Auto-updates from GitHub

WordPress does not pull GitHub by itself. This plugin uses the native pattern:

1. `Update URI: https://github.com/smvueno/harudigi-amelia-mcp-abilities`
2. A small `update_plugins_github.com` filter that reads `/releases/latest`
3. Release ZIPs attached by GitHub Actions (preferred over source zipballs)

When the plugin is listed on **WordPress.org**, remove/gate the GitHub updater so wordpress.org is the sole update source.

## Development

```bash
# Sibling of wordpress_test / harudigi-wp
git clone https://github.com/smvueno/harudigi-amelia-mcp-abilities.git
```

Release: push a tag `v1.5.0` (or create a GitHub Release). CI builds the installable ZIP with the correct folder name.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Links

- [Haru Digi](https://harudigi.com) — SMB websites, reach, automations, trust  
- [Plugin docs (GitHub Pages)](https://smvueno.github.io/harudigi-amelia-mcp-abilities/)  
- [Issues](https://github.com/smvueno/harudigi-amelia-mcp-abilities/issues)  
- [Security policy](SECURITY.md)
