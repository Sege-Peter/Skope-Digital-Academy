#!/bin/bash
# SDAC Cloud Initialization Script

echo "── SDAC Academy: Synchronizing Environment ──────────────────────────"

# 1. Apache Configuration
echo "Configuring Apache..."
sudo a2enmod rewrite
sudo cp .devcontainer/apache.conf /etc/apache2/sites-available/000-default.conf
sudo service apache2 restart

# 2. Database Sync
echo "Synchronizing MariaDB schema..."
# Wait for DB to be alive
until mysql -h db -u root -proot -e "SELECT 1" &> /dev/null; do
  echo "Waiting for database to initialize..."
  sleep 2
done

# If database is empty, import the structure
# We'll use our migration scripts or a raw SQL export if we had one.
# For now, let's just make sure the DB exists (docker-compose handles this).

echo "── SDAC Academy: Environment Synchronized! ──────────────────────────"
echo "Project available at: http://localhost:8080"
