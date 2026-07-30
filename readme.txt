=== HaruDigi Booking Abilities for Amelia and Easy MCP AI ===
Contributors: smvueno, jensmadsen, harudigi
Tags: amelia, booking, mcp, ai, abilities, easy-mcp
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extra Amelia Booking 9.7+ abilities for Easy MCP AI. Built by HaruDigi.

== Description ==

**HaruDigi Booking Abilities for Amelia and Easy MCP AI** adds gap-filler Amelia Booking abilities so AI agents (via Easy MCP AI and the WordPress Abilities API) can manage catalog, bookings, payments, and settings safely.

This plugin exists to give **Easy MCP AI** more tools.

**Requires Amelia Booking 9.7 or newer** for abilities to run. Easy MCP AI is the hard WordPress dependency; Amelia ≥ 9.7 is required at runtime.

**Disclaimer:** HaruDigi Booking Abilities for Amelia is an independent plugin by HaruDigi and is **not affiliated with or endorsed by TMS Software** (the creators of Amelia Booking).

[HaruDigi](https://harudigi.com) helps small and mid-sized businesses get a proper website, stronger reach to clients, practical automations, and trust — with people and with AI engines. *Haru* means new beginning and spring; *Digi* means digital.

= What this plugin does =

* Registers gap-filler Amelia abilities (reads + writes) for Easy MCP AI
* Leaves Amelia Pro’s native abilities alone (does not duplicate them)
* Redacts secrets (payment/OAuth/SMTP)
* Blocks password / externalId writes over MCP
* Requires `confirm=true` for destructive deletes
* Supports duration pricing tiers, extras, custom fields, and payment bookkeeping

= Requirements =

* WordPress 6.9+
* PHP 7.4+
* [Easy MCP AI](https://wordpress.org/plugins/easy-mcp-ai/) active (**required**)
* **Amelia Booking 9.7+** (required for abilities to run)

= Updates =

* **WordPress.org installs** update from wordpress.org
* **GitHub / direct ZIP installs** can update from public GitHub Releases

= About HaruDigi =

We build WordPress sites and tooling so SMBs can launch a credible website, reach more clients, automate ops, and earn trust — including with AI discovery systems.

Author: **Jens Madsen** · Brand: **HaruDigi** · [harudigi.com](https://harudigi.com)

Project site: https://smvueno.github.io/harudigi-amelia-mcp-abilities/
Source: https://github.com/smvueno/harudigi-amelia-mcp-abilities

== Installation ==

1. Install and activate **Easy MCP AI**.
2. Install and activate **Amelia Booking 9.7 or newer**.
3. Upload the `harudigi-booking-abilities-for-amelia` folder to `/wp-content/plugins/`, or install the release ZIP.
4. Activate **HaruDigi Booking Abilities for Amelia and Easy MCP AI**.
5. In Easy MCP AI, confirm Amelia abilities are enabled.
6. Optional (GitHub ZIP only): enable auto-updates in **Plugins**.

== Frequently Asked Questions ==

= What Amelia version do I need? =

**Amelia Booking 9.7 or newer.** Older Amelia versions are not supported.

= Is this an official Amelia / TMS plugin? =

No. It is an independent HaruDigi plugin and is not affiliated with or endorsed by TMS Software.

= Does this replace Amelia’s MCP tools? =

No. It extends them for Easy MCP AI. Amelia Pro’s native abilities stay registered; this plugin adds the missing admin surface.

= Why is Easy MCP AI the required plugin? =

Easy MCP AI is the MCP host that exposes abilities to your AI tools. This plugin only adds more abilities to that host. Amelia Booking 9.7+ is still required at runtime.

== Changelog ==

= 1.7.1 =
* Redact Amelia cabinet JWT secrets and map/API keys in settings summary
* Plain-English readme short description for Plugin Check

= 1.7.0 =
* Rename to HaruDigi Booking Abilities for Amelia and Easy MCP AI (`harudigi-booking-abilities-for-amelia`)
* Prefix global helpers; document Easy MCP AI host option write
* Add WordPress.org contributor smvueno

= 1.6.0 =
* Compliant name/slug: MCP Abilities for Amelia – HaruDigi (`mcp-abilities-for-amelia`)
* TMS independence disclaimer
* Require Amelia Booking 9.7+
* Separate GitHub vs WordPress.org packages (GitHub updater only on GitHub ZIP)
* Plugin Check fixes (no localhost, Tested up to 7.0, packaging)

= 1.5.3 =
* Hotfix: GitHub updater fatal from 1.5.2

= 1.5.2 =
* More reliable GitHub update checks

= 1.5.1 =
* Brand name standardized to HaruDigi; requires Easy MCP AI

= 1.5.0 =
* Public HaruDigi branded release

== Upgrade Notice ==

= 1.7.1 =
Safer settings summary redaction. Upload *-wporg.zip for the WordPress.org review update.

= 1.7.0 =
Folder slug is now harudigi-booking-abilities-for-amelia. Deactivate the old plugin folder, install the new ZIP, then activate. Use *-wporg.zip for WordPress.org.
