#!/bin/bash
# Run this on the server to apply all new schema changes
# Usage: bash run_migrations.sh

DB_USER="pharma"
DB_PASS="strongpassword123"
DB_NAME="pharmacy_mgmt"

echo "Running missing_features.sql..."
mysql -u$DB_USER -p$DB_PASS $DB_NAME < /var/www/html/pharmacy/db/missing_features.sql
echo "Done."
