=== HaruDigi Booking Abilities for Amelia and Easy MCP AI ===
Contributors: smvueno, jensmadsen, harudigi
Tags: amelia, booking, mcp, ai, abilities
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.8
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

Leave `notify` / `notifyParticipants` unset or false (the default). Only set true after the human approves. That flag only allows mail on create/status events — it does not send by itself.

= How do I send a customer email for an existing booking? =

`amelia/book` with `action=notify` (or `resend`), appointment `id`, and `confirm:true`. Optional `booking_id`. The matching customer status template must be enabled in Amelia → Notifications.

= How do payment links work? =

`amelia/pay` with `action=link` returns a checkout URL for the appointment’s remaining balance. Card checkout in a browser is out of scope for MCP.

== Changelog ==

= 2.0.8 =
* Added: `amelia/pay action=link` accepts `amount` (JPY) or `amount:"half"` for partial Stripe Checkout links
* Added: Link response includes `chargedAmount`, `remainingAfter`, `bookingTotal`, `paidSoFar`
* Added: `amelia/help` `workflow_pay` for full/half links, cash remainder, and Stripe remainder
* Added: Direct Stripe Checkout URLs (`buy.stripe.com`) returned for client send (not Amelia site wrappers)
* Fixed: `fields` + top-level args merge on writes (e.g. `customerBookingId` with nested `amount`)
* Fixed: Partial payment links bake the correct `chargedAmount` into Amelia’s callback so half-pay records and emails show the deposit, not the full total

= 2.0.7 =
* Added: `amelia/book` `action=notify` (alias `resend`) sends customer status emails for an appointment without changing status/time — requires `confirm:true`
* Added: Help / discover docs clarify that `notify:true` is a lifecycle gate only; templates must be enabled in Amelia
* Fixed: `action=update` no longer permanently clears `notifyParticipants` (was killing reminders); omitted notify mutes that edit then restores the prior flag
* Fixed: Multi-booking updates keep sibling bookings in the payload (patch first, or `booking_id` when set)
* Fixed: Manual notify is email-only and errors when no enabled customer template matches
* Fixed: `waiting` accepted as a booking status; confirm message for notify is non-deletion wording

= 2.0.6 =
* Fixed: GitHub updater checks public Releases first; `HARUDIGI_GH_TOKEN` is optional and only used if the repo is private
* Fixed: Public installs get the GitHub zip URL (`/releases/download/…`), not the private asset API

= 2.0.5 =
* Fixed: GitHub auto-updater authenticates with `HARUDIGI_GH_TOKEN` so private-repo releases are visible
* Fixed: Plugin-update cron / “Check again” no longer reuse a stale 6-hour GitHub cache
* Fixed: Updater installs the GitHub zip, not the wordpress.org zip that strips the updater

= 2.0.4 =
* Fixed: Customer notes and other customer fields can be updated (`amelia/mutate`); previously every customer update failed with 409
* Fixed: Name-only (patch) updates for location, coupon, resource, category, package, employee, and events no longer fail Amelia mandatory-field checks
* Fixed: Extra create no longer fails on null `maxQuantity`; package/service create send required `color`/`status`
* Fixed: Package `bookable` accepts `{serviceId, quantity}`; Amelia DB errors return the column hint instead of a generic failure

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

= 2.0.8 =
Use `amelia/pay action=link amount=half` (or a JPY amount) for deposit links; send the returned Stripe URL to the client.

= 2.0.7 =
Use `amelia/book action=notify id=… confirm:true` to manually email customers for the current booking status.

= 2.0.6 =
GitHub auto-updates work for the public repo with no token; HARUDIGI_GH_TOKEN remains optional for private repos.

= 2.0.5 =
GitHub updates work for private repos when `HARUDIGI_GH_TOKEN` is set in wp-config.php.

= 2.0.4 =
Customer notes and catalog/event patch updates work via `amelia/mutate` and `amelia/book`.

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
