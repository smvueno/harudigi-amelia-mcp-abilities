# Changelog

All notable changes to **HaruDigi Booking Abilities for Amelia and Easy MCP AI** are documented here.

**Single source of truth.** Edit this file only. `scripts/changelog.py sync` updates `readme.txt`. Release CI builds GitHub notes from the same section.

Format: [Keep a Changelog](https://keepachangelog.com/) · versions match plugin `Version` / git tags `vX.Y.Z`.

## [2.0.8] - 2026-09-12

### Added
- `amelia/pay action=link` accepts `amount` (JPY) or `amount:"half"` for partial Stripe Checkout links
- Link response includes `chargedAmount`, `remainingAfter`, `bookingTotal`, `paidSoFar`
- `amelia/help` `workflow_pay` for full/half links, cash remainder, and Stripe remainder
- Direct Stripe Checkout URLs (`buy.stripe.com`) returned for client send (not Amelia site wrappers)

### Fixed
- `fields` + top-level args merge on writes (e.g. `customerBookingId` with nested `amount`)
- Partial payment links bake the correct `chargedAmount` into Amelia’s callback so half-pay records and emails show the deposit, not the full total

### Upgrade notice
Use `amelia/pay action=link amount=half` (or a JPY amount) for deposit links; send the returned Stripe URL to the client.

## [2.0.7] - 2026-09-12

### Added
- `amelia/book` `action=notify` (alias `resend`) sends customer status emails for an appointment without changing status/time — requires `confirm:true`
- Help / discover docs clarify that `notify:true` is a lifecycle gate only; templates must be enabled in Amelia

### Fixed
- `action=update` no longer permanently clears `notifyParticipants` (was killing reminders); omitted notify mutes that edit then restores the prior flag
- Multi-booking updates keep sibling bookings in the payload (patch first, or `booking_id` when set)
- Manual notify is email-only and errors when no enabled customer template matches
- `waiting` accepted as a booking status; confirm message for notify is non-deletion wording

### Upgrade notice
Use `amelia/book action=notify id=… confirm:true` to manually email customers for the current booking status.

## [2.0.6] - 2026-09-11

### Fixed
- GitHub updater checks public Releases first; `HARUDIGI_GH_TOKEN` is optional and only used if the repo is private
- Public installs get the GitHub zip URL (`/releases/download/…`), not the private asset API

### Upgrade notice
GitHub auto-updates work for the public repo with no token; HARUDIGI_GH_TOKEN remains optional for private repos.

## [2.0.5] - 2026-09-11

### Fixed
- GitHub auto-updater authenticates with `HARUDIGI_GH_TOKEN` so private-repo releases are visible
- Plugin-update cron / “Check again” no longer reuse a stale 6-hour GitHub cache
- Updater installs the GitHub zip, not the wordpress.org zip that strips the updater

### Upgrade notice
GitHub updates work for private repos when `HARUDIGI_GH_TOKEN` is set in wp-config.php.

## [2.0.4] - 2026-09-11

### Fixed
- Customer notes and other customer fields can be updated (`amelia/mutate`); previously every customer update failed with 409
- Name-only (patch) updates for location, coupon, resource, category, package, employee, and events no longer fail Amelia mandatory-field checks
- Extra create no longer fails on null `maxQuantity`; package/service create send required `color`/`status`
- Package `bookable` accepts `{serviceId, quantity}`; Amelia DB errors return the column hint instead of a generic failure

### Upgrade notice
Customer notes and catalog/event patch updates work via `amelia/mutate` and `amelia/book`.

## [2.0.3] - 2026-09-10

### Added
- Single changelog source (`CHANGELOG.md`) synced to wordpress.org `readme.txt` and GitHub release notes
- CI check so releases cannot ship without human-readable notes
- Project Cursor skill for maintainers (`.cursor/skills/harudigi-amelia-mcp`)

### Upgrade notice
Changelog is now maintained in one place and published to GitHub and wordpress.org automatically.

## [2.0.2] - 2026-09-10

### Added
- WordPress.org SVN deploy from GitHub Actions (`SVN_USERNAME` / `SVN_PASSWORD`)
- Hardened production zip build and `.distignore`

### Upgrade notice
WordPress.org SVN deploy via GitHub Actions; production zip hardening.

## [2.0.1] - 2026-09-10

### Added
- Seven meta abilities only: `help`, `status`, `discover`, `query`, `mutate`, `book`, `pay`
- Payment CRUD and Stripe payment-link URLs via `amelia/pay`
- Extras quantity and simple custom-field maps on bookings

### Changed
- Booking `status` omitted → Amelia `defaultAppointmentStatus` (not hardcoded approved)
- `notify` defaults false — ask before emailing customers
- Amelia Pro native abilities unregistered while this plugin is active (Easy MCP)

### Upgrade notice
Production: 7 meta Amelia tools for Easy MCP AI; Amelia natives suppressed in Abilities API.

## [2.0.0] - 2026-09-10

### Changed
- **Breaking:** replaced ~90 fine-grained abilities with 7 meta tools

### Upgrade notice
Breaking: old fine-grained amelia/* ability slugs removed. Update agent prompts to use meta tools.

## [1.7.4] - 2026-07-30

### Fixed
- Cache Amelia status counts; Plugin Check cleanups under install slug

## [1.7.3] - 2026-07-30

### Fixed
- Plugin Check cleanups

## [1.7.2] - 2026-07-30

### Fixed
- Appointment extras dropped on update
