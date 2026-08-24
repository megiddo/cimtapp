#!/usr/bin/env bash
# Pull origin/main and rebuild the production app container on this host.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

git fetch origin
git checkout main
git pull --rebase origin main

docker compose -f docker-compose.prod.yml up --build -d
