# Changelog

All notable changes to **HaruDigi Booking Abilities for Amelia and Easy MCP AI** are documented here.

**Single source of truth.** Edit this file only. `scripts/changelog.py sync` updates `readme.txt`. Release CI builds GitHub notes from the same section.

Format: [Keep a Changelog](https://keepachangelog.com/) · versions match plugin `Version` / git tags `vX.Y.Z`.

## [2.2.0] - 2026-10-20

### Added
- Booking card in `amelia/book` create `dry_run` preview: service, provider, customer, time, extras by name, estimated total, status, email to client yes or no, and notes by label. Update previews get the same short summary.
- Custom fields can be written by field label as well as id (for example `{"Notes": "..."}`). Unknown or ambiguous labels are refused with the list of real fields.
- `amelia/query` get appointment adds `data.notesAndFields`: `privateNote` (staff only), `customerNote` (client profile), `sharedNote` (the booking field labelled Notes), plus all booking fields by name.
- `amelia/query` list responses echo `meta.appliedFilters` so agents can see which filters were really used.
- `amelia/pay` link responses add `computedFrom`, `rule`, and a warning that a payment link is not a payment until a row is paid.
- Human date words for booking lookup: `date_preset` accepts `today`, `tomorrow`, `upcoming`, `this_week`, `this_month`, and `day_N` (for example `day_10` for "the 10th"). The response echoes the real dates used.
- Slim appointment rows now carry `money` (total, paid, owed) so "what does this client owe" is one lookup.
- New tests: local harness now covers extras preview agreement, label writes, payment half rules, wrong id help, unknown parameters, availability with extras, date rules, and text validation.

### Changed
- `amount: "half"` on `amelia/pay` link now means: half of the total when nothing has been paid yet, and everything still owed once a payment has been made. The response states which rule was used.
- `amelia/status` is slim (versions, counts, default status). Full settings are still available via `amelia/query` action=settings.
- `amelia/pay` `amount` accepts a number as well as a string.

### Fixed
- Booking times are normalized by the plugin: common agent spellings (including ISO times with a timezone) are converted to one site time and shown in the preview. Ambiguous slash dates, date only values, relative words like "next friday", and impossible dates are refused at preview with `invalid_booking_start`. Search dates are validated the same way (`invalid_date`) instead of silently returning zero bookings.
- Book create preview now refuses a bad status, a missing customer or service, a provider who does not run that service, and an unknown coupon code, instead of accepting the preview and failing at confirm.
- Payment amounts that are not numbers (which were silently stored as 0), zero, or negative are refused with `invalid_amount` on add, update, and link.
- Emoji and other 4 byte characters in notes, custom fields, and customer text are refused with `invalid_characters` instead of being silently stored as question marks.
- Extras supplied inside `fields` on book update now persist like top level extras, and the preview shows the extras change for both shapes. Before, one shape previewed as a change but saved nothing, and the other saved but previewed as no change.
- Book update confirm returns `persisted` and `changed` read back from the saved appointment, so success means the change is really there.
- Unknown query parameters (for example `customerId` before it was supported, `bogus_xyz`, nested `params`) are refused with `unknown_parameter` and the supported keys, instead of being silently ignored. `customerId` on appointment lists now filters by that customer.
- `amelia/pay` with a booking id instead of a payment id returns `wrong_id_type` with the payment ids for that booking.
- Availability checks with extras no longer crash (extras are encoded the way Amelia expects).
- Extras that belong to a different service are refused at preview time with `extra_not_for_service` and the valid extras listed.
- Creating a catalog extra that already exists for the same service is refused with `duplicate_extra` and the existing id.

### Upgrade notice
Payment `amount: "half"` after a partial payment now charges the full remaining balance. Agents should rely on the returned `rule` and `paidSoFar`.

## [2.1.1] - 2026-10-19

### Fixed
- Employee (provider) create sets `externalId` to empty string so Amelia 9.9+ has the key (no PHP warning) without linking a WordPress user. Unlike customers, providers reject `-1` (truthy role check)

### Compatibility
- Verified against Amelia Booking **9.9.1** and Easy MCP AI **2.2.0** (local: meta tests 50/50, full audit 181/181, Easy MCP HTTP tools/list shows exactly the 7 meta tools)

### Upgrade notice
No agent workflow changes. Safe drop-in after Amelia 9.9.x / Easy MCP 2.x.

## [2.1.0] - 2026-09-17

### Added
- `amelia/book action=create|update` requires two-step flow: `dry_run:true` → preview (no DB write) → `confirm:true` + same `previewId` + `expectedCustomer` → real write
- `previewId` is a server-issued hash bound to (aptId + fields + customer). 10-minute TTL. Mismatch/expired/missing → refuse
- `expectedCustomer` (string) required on update: matches customer firstName/lastName/email (case-insensitive, substring). Mismatch → refuse. Blocks ID-swap writes (Charlie-Lynch class)
- `internalNotes` + `bookingStart` in same update → refused (single-concern guard)
- `amelia/query action=list entity=appointment` defaults to today…+14d window when no dates given, and to `view=slim` compact rows (aptId/bookId/start/end/status/serviceName/customer/notesPreview/paySummary) — cuts list tokens 10–50×
- Slim list response adds `{ window, page, limit, count, hasMore }` meta so models page inside the window
- `book-safety.php` houses the preview store + assertion helpers
- `scripts/full-audit.php` regression suite: 181/181 pass locally; CI flag for fails
- Tool descriptions / `discover` / `help` updated to describe `dry_run → preview → confirm` flow and ID glossary

### Fixed
- `book action=create|update` no longer silently writes on a single call; first call without `dry_run` returns `dry_run_required`

### Upgrade notice
All agents calling `amelia/book action=create|update` must now do `dry_run:true` first, show the preview to the human, then call again with `confirm:true` + `previewId` + `expectedCustomer`. Cancel/delete/notify unchanged (still require `confirm:true`).

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
