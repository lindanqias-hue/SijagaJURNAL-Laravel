<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom "sumber" masih ENUM (['Sistem', 'Guru Piket']) sementara status
     * sudah diubah jadi string pada migrasi sebelumnya.
     *
     * Sumber kehadiran kini lebih beragam: Sistem, Guru Piket, Piket,
     * Wakasek (saat memvalidasi izin guru), dan Sistem (Otomatis) untuk
     * proses akhir hari. Karena itu kolom ini dijadikan string agar tidak
     * memotong data saat sumber baru ditambahkan.
     */
    public function up(): void
    {
        Schema::table('kehadiran_gurus', function (Blueprint $table) {
            $table->string('sumber', 50)->default('Sistem')->change();
        });
    }

    public function down(): void
    {
        Schema::table('kehadiran_gurus', function (Blueprint $table) {
            $table->enum('sumber', ['Sistem', 'Guru Piket'])->default('Sistem')->change();
        });
    }
};
