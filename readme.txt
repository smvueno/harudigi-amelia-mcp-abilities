=== Haru Digi Amelia MCP Abilities ===
Contributors: jensmadsen, harudigi
Tags: amelia, booking, mcp, ai, abilities, wordpress-abilities, easy-mcp
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extend Amelia Booking with full MCP abilities for AI agents. Built by Haru Digi for SMBs.

== Description ==

**Haru Digi Amelia MCP Abilities** fills the gaps in Amelia Booking’s built-in MCP tools so AI agents (via Easy MCP AI and the WordPress Abilities API) can manage catalog, bookings, payments, and settings safely.

[Haru Digi](https://harudigi.com) helps small and mid-sized businesses get a proper website, stronger reach to clients, practical automations, and trust — with people and with AI engines. *Haru* means new beginning and spring; *Digi* means digital.

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
* [Amelia Booking](https://wpamelia.com/) active
* Easy MCP AI (or another Abilities/MCP bridge) recommended

= About Haru Digi =

We build WordPress sites and tooling so SMBs can:

* Launch a credible, fast website
* Reach more clients with clear offers and booking flows
* Automate ops without losing control
* Earn trust from customers and modern AI discovery systems

Author: **Jens Madsen** · Brand: **Haru Digi** · [harudigi.com](https://harudigi.com)

Project site: https://smvueno.github.io/harudigi-amelia-mcp-abilities/
Source: https://github.com/smvueno/harudigi-amelia-mcp-abilities

== Installation ==

1. Upload the `harudigi-amelia-mcp-abilities` folder to `/wp-content/plugins/`, or install the release ZIP.
2. Activate **Haru Digi Amelia MCP Abilities**.
3. Ensure Amelia Booking is active.
4. In Easy MCP AI, confirm Amelia abilities are enabled (the plugin merges new slugs on activation / version bump).
5. Optional: enable auto-updates for this plugin in **Plugins** (updates come from GitHub Releases until listed on WordPress.org).

== Frequently Asked Questions ==

= Does this replace Amelia’s MCP tools? =

No. It extends them. Amelia Pro’s native abilities stay registered; this plugin adds the missing admin surface.

= Will updates work from GitHub? =

Yes, while the plugin is distributed from GitHub. WordPress uses the `Update URI` header and a small built-in checker against public GitHub Releases. When the plugin is listed on WordPress.org, wordpress.org becomes the update source.

= Is this affiliated with Amelia? =

No. This is an independent Haru Digi plugin that integrates with Amelia Booking.

== Changelog ==

= 1.5.0 =
* Public Haru Digi branded release (`harudigi-amelia-mcp-abilities`)
* GitHub Releases auto-update via native `Update URI` filter
* Docs site and wp.org-ready readme.txt

= 1.4.2 =
* Release zip after Stripe payment-link E2E verification

= 1.4.1 =
* `amelia/get-payment-link` for remaining balance URLs

= 1.4.0 =
* Payment add/update/delete abilities; optional payment on create booking

== Upgrade Notice ==

= 1.5.0 =
Folder and slug renamed to harudigi-amelia-mcp-abilities. Deactivate the old amelia-mcp-abilities plugin, install this one, then activate.
