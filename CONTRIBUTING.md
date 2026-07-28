# Contributing

Thanks for helping improve **Amelia MCP Abilities - HaruDigi**.

[HaruDigi](https://harudigi.com) builds WordPress sites and tools so SMBs can get a proper website, stronger client reach, automations, and trust (including with AI systems). Contributions that keep that bar — safe, clear, maintainable — are welcome.

## Ground rules

1. Keep ability slugs under `amelia/*` unless there is a strong migration plan.
2. Never expose payment/OAuth/SMTP secrets in ability output.
3. Destructive actions must require `confirm=true`.
4. Keep PHP files modular and under ~500 lines where practical.
5. Match existing coding style (WordPress PHP conventions).
6. In prose, the brand is **HaruDigi** (one word, camel case). Logo lettering may stay **HARUDIGI**.

## Development

1. Fork and clone this repo.
2. Symlink or bind-mount into a WordPress install with Easy MCP AI (+ Amelia Booking for runtime tests).
3. Make a focused change; open a PR against `main`.

## Releases

Maintainers tag `vX.Y.Z`. GitHub Actions builds `harudigi-amelia-mcp-abilities-X.Y.Z.zip` and attaches it to the Release (required for in-dashboard updates).

## Code of conduct

Be respectful. Harassment or spam will be removed.

— Jens Madsen · HaruDigi · https://harudigi.com
