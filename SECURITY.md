# Security Policy

## Supported versions

We support the latest release of **Haru Digi Amelia MCP Abilities** on the `main` branch and GitHub Releases.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Email: **hello@harudigi.com** (subject: `Security — harudigi-amelia-mcp-abilities`)

Include:

- Plugin version
- WordPress / PHP / Amelia versions
- Steps to reproduce
- Impact assessment (data exposure, privilege escalation, etc.)

We aim to acknowledge reports within a few business days.

## Hardening notes (by design)

- Payment / OAuth / SMTP secrets are redacted from MCP responses
- Password and `externalId` writes are blocked over MCP
- Destructive deletes require explicit `confirm=true`
- Prefer cancel / hide / disable over permanent delete when possible

Built by [Haru Digi](https://harudigi.com) — Jens Madsen.
