# Contributing

Thanks for helping improve **HaruDigi Booking Abilities for Amelia and Easy MCP AI**.

[HaruDigi](https://harudigi.com) builds WordPress sites and tools for SMBs. This plugin is independent and **not affiliated with or endorsed by TMS Software**.

## Ground rules

1. Keep ability slugs under `amelia/*` unless there is a strong migration plan.
2. Never expose payment/OAuth/SMTP secrets in ability output.
3. Destructive actions must require `confirm=true`.
4. Keep PHP files modular and under ~500 lines where practical.
5. Match WordPress PHP conventions.
6. Brand spelling in prose: **HaruDigi**. Logo lettering may stay **HARUDIGI**.
7. Public title/slug must stay trademark-safe: **HaruDigi Booking Abilities for Amelia and Easy MCP AI** / `harudigi-booking-abilities-for-amelia` (never start the name or slug with “Amelia”).
8. Document **Amelia Booking 9.7+** as the minimum Amelia version.

## Builds

```bash
./scripts/build-zip.sh        # GitHub ZIP (with updater)
./scripts/build-zip.sh wporg  # WordPress.org ZIP (no updater / Update URI)
```

## Releases

Tag `vX.Y.Z`. CI attaches both ZIPs to the GitHub Release.

— Jens Madsen · HaruDigi · https://harudigi.com
