#!/usr/bin/env bash
# Commit the prepared WordPress.org SVN working copy using credentials from
# ~/.config/harudigi/wporg-svn.env (outside the plugin tree — never zip this).
set -euo pipefail

ENV_FILE="${WPORG_SVN_ENV:-$HOME/.config/harudigi/wporg-svn.env}"
SLUG="${WPORG_SVN_SLUG:-harudigi-booking-abilities-for-amelia}"
WORKDIR="${WPORG_SVN_WORKDIR:-$HOME/svn-wporg-$SLUG}"
MSG="${1:-Initial release 2.0.1: seven meta tools for Easy MCP AI.}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE — copy wporg-svn.env.example and set WPORG_SVN_PASSWORD." >&2
  exit 1
fi
# shellcheck disable=SC1090
set -a
# strip CR; allow KEY=value
# shellcheck source=/dev/null
source <(grep -E '^[A-Za-z_][A-Za-z0-9_]*=' "$ENV_FILE" | tr -d '\r')
set +a

USER="${WPORG_SVN_USERNAME:-}"
PASS="${WPORG_SVN_PASSWORD:-}"
if [[ -z "$USER" || -z "$PASS" ]]; then
  echo "Set WPORG_SVN_USERNAME and WPORG_SVN_PASSWORD in $ENV_FILE" >&2
  exit 1
fi
if [[ ! -d "$WORKDIR/.svn" ]]; then
  echo "SVN working copy not found: $WORKDIR" >&2
  exit 1
fi

export PATH="/opt/homebrew/bin:${PATH}"
cd "$WORKDIR"
svn ci -m "$MSG" --username "$USER" --password "$PASS" --non-interactive --no-auth-cache
echo "Committed. Listing may take a while: https://wordpress.org/plugins/${SLUG}/"
