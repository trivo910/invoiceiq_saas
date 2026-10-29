#!/usr/bin/env bash
# Safe first-run helper. Configure MySQL and .env before running migrations.
set -euo pipefail

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example. Review database, app URL, mail and billing settings."
fi

composer install --no-interaction --prefer-dist --optimize-autoloader

# Keep an existing APP_KEY: replacing it can make encrypted application data unreadable.
if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  php artisan key:generate
fi

php artisan migrate --force
echo "Migrations complete. No demo/sample records or platform admin were created."
echo "Create the first platform admin interactively with: php artisan superadmin:create"
echo "For local sample plans only, explicitly run the BillingDemoSeeder after enabling local demo mode."
