#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

if ! xcode-select -p >/dev/null 2>&1; then
  echo "Apple Command Line Tools chua san sang."
  echo "Chay: xcode-select --install"
  echo "Neu muon it dung terminal hon, doc docs/MAC_LOCAL_SETUP.md va cai MAMP."
  exit 1
fi

if ! command -v brew >/dev/null 2>&1; then
  echo "Homebrew chua duoc cai. Hay cai Homebrew truoc: https://brew.sh"
  echo "Neu khong muon cai Homebrew, doc docs/MAC_LOCAL_SETUP.md va dung MAMP/phpMyAdmin."
  exit 1
fi

echo "Dang cai PHP, MySQL va Composer..."
brew install php mysql composer

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

echo "Dang khoi dong MySQL..."
brew services start mysql >/dev/null || true

echo "Cho MySQL san sang..."
for i in $(seq 1 30); do
  if mysqladmin ping -uroot --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

if ! mysqladmin ping -uroot --silent >/dev/null 2>&1; then
  echo "MySQL chua san sang hoac root dang co mat khau."
  echo "Neu root co mat khau, hay sua DB_PASS trong file .env roi chay lai."
  exit 1
fi

echo "Dang tao database va bang..."
mysql -uroot < database/schema.sql

echo "Dang tao admin dau tien..."
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}" \
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin@123456}" \
php scripts/seed_admin.php

echo ""
echo "Cai dat xong."
echo "Database: sales_system"
echo "Admin email: ${ADMIN_EMAIL:-admin@example.com}"
echo "Admin password: ${ADMIN_PASSWORD:-Admin@123456}"
echo ""
echo "Chay web local:"
echo "php -S localhost:8000 -t public"
