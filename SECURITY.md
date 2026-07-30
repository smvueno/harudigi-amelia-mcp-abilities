# Security Policy

## Supported versions

Latest release of **HaruDigi Booking Abilities for Amelia and Easy MCP AI** (`harudigi-booking-abilities-for-amelia`) on GitHub Releases.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Email: **hello@harudigi.com** (subject: `Security — harudigi-booking-abilities-for-amelia`)

Include plugin version, WordPress / PHP / Easy MCP AI / Amelia versions (Amelia must be 9.7+), steps to reproduce, and impact.

## Hardening notes (by design)

- Payment / OAuth / SMTP secrets are redacted from MCP responses
- Password and `externalId` writes are blocked over MCP
- Destructive deletes require explicit `confirm=true`

Built by [HaruDigi](https://harudigi.com) — Jens Madsen.  
Independent plugin — not affiliated with or endorsed by TMS Software.
