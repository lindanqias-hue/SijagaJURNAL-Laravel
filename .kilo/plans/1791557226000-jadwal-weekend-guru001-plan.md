# Tambah Jadwal Weekend untuk GURU001

## Context

Hari ini Sabtu. Komponen `input-jurnal` sudah mendukung Sabtu/Minggu (`namaHariUntukTanggal()` memetakan `Saturday → Sabtu`, `Sunday → Minggu` di `⚡input-jurnal.blade.php:772-773`), dan `findJadwalTerpilih()` mencari `jadwal` berdasarkan `id_guru` + `hari` + `jam_ke`. Masalahnya murni data: `DatabaseSeeder::seedJadwal()` hanya mengisi hari **Senin–Jumat**, sehingga di weekend tidak ada jadwal mengajar → halaman jurnal menampilkan "Tidak ada jadwal mengajar untuk hari ini".

## Task

1. **Edit `database/seeders/DatabaseSeeder.php`** — tambahkan 6 baris ke array `seedJadwal($kelasRpl2Id, [...], $guruIds)` (setelah baris GURU001 Jumat jam 11–12, yaitu baris 150–151):

   ```php
   ['GURU001', 'Sabtu', 1, '07:00:00', '07:40:00'],
   ['GURU001', 'Sabtu', 2, '07:40:00', '08:20:00'],
   ['GURU001', 'Sabtu', 3, '08:20:00', '09:00:00'],
   ['GURU001', 'Minggu', 1, '07:00:00', '07:40:00'],
   ['GURU001', 'Minggu', 2, '07:40:00', '08:20:00'],
   ['GURU001', 'Minggu', 3, '08:20:00', '09:00:00'],
   ```

2. **Jalankan seeder** (pakai `updateOrInsert` pada unique key `id_kelas + hari + jam_ke`, jadi tidak terjadi duplikasi):

   ```bash
   php artisan db:seed --class=DatabaseSeeder
   ```

## Verifikasi

- Login sebagai GURU001 (NIP `GURU001`, password `guru123`), buka **Input Jurnal** → form jurnal Sabtu muncul dengan jam ke-1 s/d ke-3.
- GURU001 **bukan** piket Sabtu (piket Sabtu = GURU006 via `WeekendGuruPiketSeeder`), sehingga absensi siswa tampil sebagai **select Hadir/Alpa** — sesuai untuk menguji fitur absensi non-piket yang baru ditambahkan.
- Cek data: `php artisan tinker --execute 'dump(DB::table("jadwal")->where("hari","Sabtu")->get());'`

## Risks

- Seed ulang aman (idempotent via `updateOrInsert`).
- Jadwal weekend ditambahkan untuk **XI RPL 2** (kelas tempat GURU001 paling banyak mengajar). Jika juga diperlukan di XI RPL 1, tambahkan baris serupa ke array `seedJadwal($kelasRpl1Id, ...)`.
- Tidak ada perubahan kode aplikasi — hanya data seeder.
