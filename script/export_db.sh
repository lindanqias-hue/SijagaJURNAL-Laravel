#!/bin/bash

# Load variabel dari file .env
export $(grep -v '^#' .env | xargs)

echo "export db :D, pls wait :3...."
echo ""

# Buat folder database jika belum ada
mkdir -p database

# Jalankan mysqldump langsung dari sistem lokal
mysqldump -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" > database/init.sql

if [ $? -eq 0 ]; then
  echo "db exported to database/init.sql successfully, see you later :D"
else
  echo "Export gagal! Cek kembali konfigurasi di file .env kamu."
fi