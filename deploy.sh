#!/bin/bash
# ─────────────────────────────────────────────────────────────────
# IT HelpDesk — manual production deploy (run ON the NAS)
#
#   ssh <user>@192.168.2.213
#   cd /volume1/docker/it-helpdesk && ./deploy.sh
#
# Mirrors the steps in .github/workflows/deploy.yml. The NAS is
# LAN-only, so GitHub-hosted runners cannot SSH in — until a
# self-hosted runner is set up, this script IS the deploy.
# ─────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")"

echo "── 1/7 Pull latest main ──────────────────────────"
git fetch --prune origin
git reset --hard origin/main

echo "── 2/7 Rebuild images (php + nginx/SPA) ──────────"
docker compose build --no-cache app nginx

echo "── 3/7 Restart containers ────────────────────────"
docker compose up -d --remove-orphans

echo "── 4/7 Wait for app container ────────────────────"
TRIES=0
until docker compose exec -T app php -r "echo 'ok';" 2>/dev/null | grep -q ok; do
  TRIES=$((TRIES+1))
  if [ "$TRIES" -ge 15 ]; then echo "app container not healthy — aborting"; exit 1; fi
  sleep 2
done

echo "── 5/7 Composer install ──────────────────────────"
docker compose exec -T app composer install \
  --prefer-dist --no-dev --no-progress --no-interaction --optimize-autoloader

echo "── 6/7 Migrate + rebuild caches ──────────────────"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache

echo "── 7/7 Restart queue workers ─────────────────────"
docker compose exec -T app php artisan queue:restart
docker compose exec -T app php artisan storage:link --quiet || true

echo "✅ Deploy complete: $(git rev-parse --short HEAD)"
