#!/bin/bash
set -e

# ── Use Railway's dynamic PORT (defaults to 80 if not set) ───────────────────
export PORT="${PORT:-80}"

# Update Apache to listen on Railway's assigned port
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf

# ── Symfony production setup ──────────────────────────────────────────────────
# Warm up the production cache
php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || \
php bin/console cache:warmup --env=dev --no-debug 2>/dev/null || true

# Run database migrations automatically on every deploy
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || \
php bin/console doctrine:migrations:migrate --no-interaction --env=dev 2>/dev/null || true

# Fix var/ permissions for www-data
chown -R www-data:www-data /var/www/html/var 2>/dev/null || true

echo "✅ Travelia is starting on port ${PORT}..."

# ── Start Apache ──────────────────────────────────────────────────────────────
exec apache2-foreground
