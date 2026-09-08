#!/bin/bash

# Load variabel dari file .env
export $(grep -v '^#' .env | xargs)

echo "importing db :D, pls wait :3...."
echo ""

# Import file init.sql ke MySQL
mysql -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" < database/init.sql

if [ $? -eq 0 ]; then
  echo "db imported successfully, see you later :D"
else
  echo "Import gagal! Pastikan file database/init.sql ada dan MySQL lokal aktif."
fi