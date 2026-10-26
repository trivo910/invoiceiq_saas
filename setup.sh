#!/bin/bash
# InvoiceIQ — Laravel 11 Setup (MySQL)
# Requires: PHP 8.2+, Composer, MySQL 8.0+

set -e
GREEN='\033[0;32m'; BLUE='\033[0;34m'; YELLOW='\033[1;33m'; NC='\033[0m'

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e " InvoiceIQ — MySQL Setup"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [ ! -f .env ]; then
  if [ ! -f .env.example ]; then
    echo -e "${YELLOW}Missing .env.example. Run this script from the InvoiceIQ project root.${NC}"
    exit 1
  fi
  cp .env.example .env
  echo -e "${GREEN}✓ .env created${NC}"
fi

echo -e "\n${YELLOW}Enter MySQL credentials:${NC}"
read -p "  Host     [127.0.0.1]: " H; H=${H:-127.0.0.1}
read -p "  Port     [3306]:      " P; P=${P:-3306}
read -p "  Database [invoiceiq]: " D; D=${D:-invoiceiq}
read -p "  Username [root]:      " U; U=${U:-root}
read -s -p "  Password:             " W; echo ""

sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/^DB_HOST=.*/DB_HOST=${H}/"             .env
sed -i "s/^DB_PORT=.*/DB_PORT=${P}/"             .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${D}/"     .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${U}/"     .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${W}/"     .env
echo -e "${GREEN}✓ .env updated${NC}"

echo -e "\nCreating MySQL database..."
mysql -h"${H}" -P"${P}" -u"${U}" -p"${W}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${D}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null \
  && echo -e "${GREEN}✓ Database '${D}' ready${NC}" \
  || echo -e "${YELLOW}⚠  Create the DB manually: CREATE DATABASE ${D} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;${NC}"

echo -e "\nInstalling Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo -e "${YELLOW}Billing starts with unconfigured paid plans. Review docs/ai/BILLING_AND_PLANS.md and add approved plan, tax, and Razorpay settings before enabling checkout.${NC}"

echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e " Setup complete!"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\n Run:  ${GREEN}php artisan serve${NC}"
echo -e " Open: ${GREEN}http://localhost:8000${NC}\n"
