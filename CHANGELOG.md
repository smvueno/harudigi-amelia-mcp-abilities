# Changelog

All notable changes to **HaruDigi Booking Abilities for Amelia and Easy MCP AI** are documented here.

**Single source of truth.** Edit this file only. `scripts/changelog.py sync` updates `readme.txt`. Release CI builds GitHub notes from the same section.

Format: [Keep a Changelog](https://keepachangelog.com/) · versions match plugin `Version` / git tags `vX.Y.Z`.

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
