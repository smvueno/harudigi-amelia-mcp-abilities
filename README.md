# HaruDigi Booking Abilities for Amelia and Easy MCP AI

![HaruDigi Booking Abilities for Amelia and Easy MCP AI](docs/banner.png)

**Compact Amelia admin MCP for Easy MCP AI — 7 tools, not 100.**  
Query, mutate, book, and pay without flooding agent context. Independent plugin by [HaruDigi](https://harudigi.com). Not affiliated with TMS Software.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![GitHub release](https://img.shields.io/github/v/release/smvueno/harudigi-amelia-mcp-abilities)](https://github.com/smvueno/harudigi-amelia-mcp-abilities/releases)

**Docs:** [smvueno.github.io/harudigi-amelia-mcp-abilities](https://smvueno.github.io/harudigi-amelia-mcp-abilities/)  
**Author:** Jens Madsen · **Brand:** HaruDigi · **Site:** [harudigi.com](https://harudigi.com)  
**Install slug:** `harudigi-booking-abilities-for-amelia` · **Version:** 2.0.7

---

## Tools (v2)

| Ability | Role |
|---------|------|
| `amelia/help` | How to use; safety (notify / confirm) |
| `amelia/status` | Versions + `defaultAppointmentStatus` |
| `amelia/discover` | Per-entity actions + examples |
| `amelia/query` | `list` / `get` / `stats` / `availability` / `booking_options` / `settings` |
| `amelia/mutate` | Catalog + customers CRUD (`create` / `update` / `delete` / `status`) |
| `amelia/book` | Appointments + events (extras, CFs, duration, status) |
| `amelia/pay` | Payments + Stripe **payment link** URLs |

**Breaking (2.0):** fine-grained `amelia/update-*` (and similar) abilities removed. Agents must use the seven meta tools above.

### Entities (`query` / `mutate`)

`service`, `category`, `location`, `employee`, `customer`, `package`, `extra`, `resource`, `coupon`, `custom_field`, `appointment` (read via query; write via `book`), `event` (read via query; write via `book`), `notification` (query list only).

### Booking (`amelia/book`)

Actions: `create`, `update`, `cancel`, `delete`, `set_status`, `notify`, `create_event`, `update_event`, `delete_event`, `book_event`.

- **Create needs:** `serviceId`, `providerId`, `customerId`, `bookingStart` (`YYYY-MM-DD HH:mm`)
- Prefer `amelia/query` `booking_options` + `availability` first
- **Extras:** `[{ "extraId": 1, "quantity": 2 }]`
- **Custom fields:** simple map `{"3":"Gion"}` (field id → value) or full Amelia objects; updates merge unless `replaceCustomFields: true`
- **`status` omitted** → Amelia Settings → `defaultAppointmentStatus` (never assume `approved`)
- **`notify` / `notifyParticipants` default `false`** — ask the human before `true` (gate on create/status events only; does not send by itself)
- **`action=notify`** (alias `resend`) + `confirm:true` — send customer status emails now for appointment `id` (optional `booking_id`). Amelia template for that status must be **enabled**.

### Payments (`amelia/pay`)

Actions: `list`, `get`, `add`, `update`, `delete`, `link`.

- `link` returns a Stripe (or configured gateway) checkout **URL** for the remaining balance — no card capture in-MCP
- `delete` requires `confirm: true`

---

## Requirements

| Requirement | Notes |
|-------------|--------|
| WordPress 6.9+ | Tested through 7.1 |
| PHP 7.4+ | |
| **Easy MCP AI** | **Required** MCP host |
| Amelia Booking **9.7+** | Required for abilities to run |

## Install

1. Activate **Easy MCP AI** and **Amelia Booking 9.7+**.
2. Upload / activate this plugin (`harudigi-booking-abilities-for-amelia`).
3. Easy MCP AI → Abilities: enable only the 7 `amelia/*` meta tools.
4. Create an Easy MCP bearer token; point the MCP client at  
   `{site}/wp-json/easy-mcp-ai/v1/mcp`.

## Safety

- `notify` defaults **false** (lifecycle gate only — not a send button)
- Manual send: `amelia/book` `action=notify` + `confirm:true`
- Booking `status` omitted → site `defaultAppointmentStatus`
- Deletes / cancel / notify require `confirm: true`
- Secrets redacted; customer **password** and WP **`externalId`** writes blocked (`externalId` forced unlinked `-1`)

## Amelia Pro MCP vs this plugin

Amelia Pro 9.x also ships a **separate** MCP HTTP stack (`/wp-json/mcp/amelia-mcp-server`, Application Passwords / logged-in user) with ~12 native abilities. That path is **Amelia’s product** (e.g. Angie); this plugin does **not** configure or disable Amelia’s MCP adapter.

While **this** plugin is active it **unregisters** those 12 native abilities from the WordPress Abilities API so **Easy MCP AI** does not expose them next to our meta tools (they duplicate coverage and hardcode `approved` on appointment create). Deactivate this plugin to restore the natives for Amelia’s own MCP / other hosts.

| | Amelia Pro MCP | This plugin (Easy MCP) |
|--|----------------|------------------------|
| Endpoint | `/wp-json/mcp/amelia-mcp-server` | `/wp-json/easy-mcp-ai/v1/mcp` |
| Tool count | ~12 natives | 7 meta |
| Scope | List / book / cancel focused | Full catalog + book + pay |
| Create status | Hardcoded `approved` | Site default |

## Agent quickstart

1. `amelia/help` then `amelia/status`
2. `amelia/discover` for the entity you need
3. Book: `query` → `booking_options` / `availability` → `book` `create` (leave `notify` false)
4. Money: `pay` `list` / `add` / `link` as needed

## Changelog / releases

Edit **`CHANGELOG.md` only**, then `python3 scripts/changelog.py check`.  
Tag `vX.Y.Z` → GitHub notes + wordpress.org `readme.txt` stay in sync. See `.cursor/skills/harudigi-amelia-mcp/SKILL.md`.

## Dev / test scripts

Local (Local by Flywheel) helpers under `scripts/` — not for production:

- `local-test-meta.php` — meta ability matrix
- `pentest-mcp.php` — Easy MCP auth + misuse checks

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
