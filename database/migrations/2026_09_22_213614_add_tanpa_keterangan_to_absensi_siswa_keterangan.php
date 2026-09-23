<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE absensi_siswa MODIFY keterangan ENUM('Hadir', 'Izin', 'Sakit', 'Alpa', 'Dispensasi', 'Tanpa Keterangan') NOT NULL DEFAULT 'Hadir'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE absensi_siswa MODIFY keterangan ENUM('Hadir', 'Izin', 'Sakit', 'Alpa', 'Dispensasi') NOT NULL DEFAULT 'Hadir'");
    }
};
