=== Amelia MCP Abilities - HaruDigi ===
Contributors: jensmadsen, harudigi
Tags: amelia, booking, mcp, ai, abilities, wordpress-abilities, easy-mcp
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extra Amelia Booking abilities for Easy MCP AI. Built by HaruDigi for SMBs.

== Description ==

**Amelia MCP Abilities - HaruDigi** adds gap-filler Amelia Booking abilities so AI agents (via Easy MCP AI and the WordPress Abilities API) can manage catalog, bookings, payments, and settings safely.

This plugin exists to give **Easy MCP AI** more tools. Amelia Booking should be installed for those abilities to run; it is not a hard WordPress dependency of this plugin.

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
* [Easy MCP AI](https://wordpress.org/plugins/easy-mcp-ai/) active (required)
* Amelia Booking recommended (abilities call Amelia when present)

= About HaruDigi =

We build WordPress sites and tooling so SMBs can:

* Launch a credible, fast website
* Reach more clients with clear offers and booking flows
* Automate ops without losing control
* Earn trust from customers and modern AI discovery systems

Author: **Jens Madsen** · Brand: **HaruDigi** · [harudigi.com](https://harudigi.com)

Project site: https://smvueno.github.io/harudigi-amelia-mcp-abilities/
Source: https://github.com/smvueno/harudigi-amelia-mcp-abilities

== Installation ==

1. Install and activate **Easy MCP AI**.
2. Upload the `harudigi-amelia-mcp-abilities` folder to `/wp-content/plugins/`, or install the release ZIP.
3. Activate **Amelia MCP Abilities - HaruDigi**.
4. Install/activate Amelia Booking so the abilities have something to talk to.
5. In Easy MCP AI, confirm Amelia abilities are enabled (the plugin merges new slugs on activation / version bump).
6. Optional: enable auto-updates for this plugin in **Plugins** (updates come from GitHub Releases until listed on WordPress.org).

== Frequently Asked Questions ==

= Does this replace Amelia’s MCP tools? =

No. It extends them for Easy MCP AI. Amelia Pro’s native abilities stay registered; this plugin adds the missing admin surface.

= Why does it require Easy MCP AI instead of Amelia? =

Easy MCP AI is the MCP host that exposes abilities to your AI tools. This plugin only adds more abilities to that host. Amelia Booking is what those abilities operate on — keep it active for full use.

= Will updates work from GitHub? =

Yes, while the plugin is distributed from GitHub. WordPress uses the `Update URI` header and a small built-in checker against public GitHub Releases. When the plugin is listed on WordPress.org, wordpress.org becomes the update source.

= Is this affiliated with Amelia? =

No. This is an independent HaruDigi plugin that integrates with Amelia Booking and Easy MCP AI.

== Changelog ==

= 1.5.3 =
* Hotfix: GitHub updater fatal from 1.5.2

= 1.5.2 =
* More reliable GitHub update checks (Update URI + transient injection)
* Fix failed-API cache so a temporary GitHub error does not block later checks

= 1.5.1 =
* Brand name standardized to HaruDigi
* Plugin title: Amelia MCP Abilities - HaruDigi (sorts with Amelia)
* Requires Easy MCP AI (Amelia is recommended, not a hard dependency)

= 1.5.0 =
* Public HaruDigi branded release (`harudigi-amelia-mcp-abilities`)
* GitHub Releases auto-update via native `Update URI` filter
* Docs site and wp.org-ready readme.txt

= 1.4.2 =
* Release zip after Stripe payment-link E2E verification

= 1.4.1 =
* `amelia/get-payment-link` for remaining balance URLs

= 1.4.0 =
* Payment add/update/delete abilities; optional payment on create booking

== Upgrade Notice ==

= 1.5.3 =
Hotfix for GitHub updater. Re-upload this ZIP if you installed 1.5.2 and hit a critical error on Updates.

= 1.5.2 =
Improves GitHub → WordPress update detection. If you are on 1.5.0/1.5.1, use Dashboard → Updates → Check again.

= 1.5.1 =
Requires Easy MCP AI. Plugin renamed to Amelia MCP Abilities - HaruDigi. Brand text uses HaruDigi.
