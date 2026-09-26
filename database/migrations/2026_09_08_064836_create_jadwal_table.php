<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jadwal', function (Blueprint $table) {
            $table->increments('id_jadwal');

            // Harus sama dengan pengguna.id_pengguna
            $table->unsignedInteger('id_guru');

            // Harus sama dengan kelas.id_kelas
            $table->unsignedInteger('id_kelas');

            $table->enum('hari', [
                'Senin',
                'Selasa',
                'Rabu',
                'Kamis',
                'Jumat',
                'Sabtu'
            ]);

            $table->unsignedInteger('jam_ke');

            $table->time('jam_mulai');
            $table->time('jam_selesai');

            // Relasi ke tabel pengguna
            $table->foreign('id_guru')
                ->references('id_pengguna')
                ->on('pengguna')
                ->cascadeOnDelete();

            // Relasi ke tabel kelas
            $table->foreign('id_kelas')
                ->references('id_kelas')
                ->on('kelas')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};