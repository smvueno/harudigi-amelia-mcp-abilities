=== HaruDigi Booking Abilities for Amelia and Easy MCP AI ===
Contributors: smvueno, jensmadsen, harudigi
Tags: amelia, booking, mcp, ai, abilities
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compact Amelia Booking MCP for Easy MCP AI (7 meta abilities). Built by HaruDigi.

== Description ==

**HaruDigi Booking Abilities for Amelia and Easy MCP AI** exposes seven meta abilities (`help`, `status`, `discover`, `query`, `mutate`, `book`, `pay`) so AI agents can manage Amelia without 100+ tools.

While active, this plugin unregisters Amelia Pro’s native abilities from the WordPress Abilities API so Easy MCP AI only sees the meta tools (natives duplicate coverage and hardcode approved on create). Amelia’s separate MCP HTTP endpoint is left alone; deactivate this plugin to restore natives.

**Requires Amelia Booking 9.7+.** Easy MCP AI is required.

**Disclaimer:** Independent plugin by HaruDigi — not affiliated with or endorsed by TMS Software.

= What this plugin does =

* Registers 7 meta Amelia abilities for Easy MCP AI
* Suppresses Amelia Pro native abilities in the Abilities API while active
* Redacts secrets; blocks password / externalId writes
* Requires confirm=true for deletes/cancels
* notify defaults false; status follows Amelia defaultAppointmentStatus
* Payment link URLs (Stripe etc.) via amelia/pay action=link

= Requirements =

* WordPress 6.9+
* PHP 7.4+
* [Easy MCP AI](https://wordpress.org/plugins/easy-mcp-ai/) active (**required**)
* **Amelia Booking 9.7+** (required for abilities to run)

= Updates =

* **WordPress.org installs** update from wordpress.org
* **GitHub / direct ZIP installs** can update from public GitHub Releases

Author: **Jens Madsen** · Brand: **HaruDigi** · [harudigi.com](https://harudigi.com)

== Installation ==

1. Install and activate **Easy MCP AI**.
2. Install and activate **Amelia Booking 9.7 or newer**.
3. Upload the `harudigi-booking-abilities-for-amelia` folder to `/wp-content/plugins/`, or install the release ZIP.
4. Activate **HaruDigi Booking Abilities for Amelia and Easy MCP AI**.
5. In Easy MCP AI → Abilities, confirm only the 7 meta `amelia/*` tools are enabled.
6. Create a bearer token; connect clients to `/wp-json/easy-mcp-ai/v1/mcp`.

== Frequently Asked Questions ==

= What Amelia version do I need? =

**Amelia Booking 9.7 or newer.**

= Is this an official Amelia / TMS plugin? =

No. Independent HaruDigi plugin — not affiliated with or endorsed by TMS Software.

= Does this replace Amelia’s MCP tools? =

For **Easy MCP AI**: yes while active — Amelia’s 12 native abilities are unregistered from the Abilities API so agents only see the 7 meta tools. Amelia Pro’s own MCP HTTP route (`/wp-json/mcp/...`) is not configured by this plugin. Deactivate to restore natives.

= Why is Easy MCP AI required? =

Easy MCP AI is the MCP host. This plugin adds Amelia abilities to that host.

= How do I book without emailing the customer? =

Leave `notify` / `notifyParticipants` unset or false (the default). Only set true after the human approves.

= How do payment links work? =

`amelia/pay` with `action=link` returns a checkout URL for the appointment’s remaining balance. Card checkout in a browser is out of scope for MCP.

== Changelog ==

= 2.0.3 =
* Added: Single changelog source (`CHANGELOG.md`) synced to wordpress.org `readme.txt` and GitHub release notes
* Added: CI check so releases cannot ship without human-readable notes
* Added: Project Cursor skill for maintainers (`.cursor/skills/harudigi-amelia-mcp`)

= 2.0.2 =
* Added: WordPress.org SVN deploy from GitHub Actions (`SVN_USERNAME` / `SVN_PASSWORD`)
* Added: Hardened production zip build and `.distignore`

= 2.0.1 =
* Added: Seven meta abilities only: `help`, `status`, `discover`, `query`, `mutate`, `book`, `pay`
* Added: Payment CRUD and Stripe payment-link URLs via `amelia/pay`
* Added: Extras quantity and simple custom-field maps on bookings
* Changed: Booking `status` omitted → Amelia `defaultAppointmentStatus` (not hardcoded approved)
* Changed: `notify` defaults false — ask before emailing customers
* Changed: Amelia Pro native abilities unregistered while this plugin is active (Easy MCP)

= 2.0.0 =
* Changed: Breaking: replaced ~90 fine-grained abilities with 7 meta tools

= 1.7.4 =
* Fixed: Cache Amelia status counts; Plugin Check cleanups under install slug

= 1.7.3 =
* Fixed: Plugin Check cleanups

= 1.7.2 =
* Fixed: Appointment extras dropped on update

== Upgrade Notice ==

= 2.0.3 =
Changelog is now maintained in one place and published to GitHub and wordpress.org automatically.

= 2.0.2 =
WordPress.org SVN deploy via GitHub Actions; production zip hardening.

= 2.0.1 =
Production: 7 meta Amelia tools for Easy MCP AI; Amelia natives suppressed in Abilities API.

= 2.0.0 =
Breaking: old fine-grained amelia/* ability slugs removed. Update agent prompts to use meta tools.

= 1.7.4 =
Fixed: Cache Amelia status counts; Plugin Check cleanups under install slug

= 1.7.3 =
Fixed: Plugin Check cleanups

= 1.7.2 =
Fixed: Appointment extras dropped on update
