<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->enum('keterangan', [
                'Hadir',
                'Izin',
                'Sakit',
                'Alpa',
                'Dispensasi',
                'Tanpa Keterangan'
            ])->default('Hadir')->change();
        });
    }

    public function down(): void
    {
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->enum('keterangan', [
                'Hadir',
                'Izin',
                'Sakit',
                'Alpa',
                'Dispensasi'
            ])->default('Hadir')->change();
        });
    }
};
