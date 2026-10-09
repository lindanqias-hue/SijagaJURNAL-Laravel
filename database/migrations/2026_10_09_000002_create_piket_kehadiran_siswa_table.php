<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kehadiran siswa yang dicatat oleh GURU PIKET.
     *
     * Tabel ini terpisah dari absensi_siswa karena absensi_siswa
     * mensyaratkan id_jurnal (FK), sedangkan piket mencatat
     * kehadiran SEBELUM guru mengisi jurnal.
     *
     * Satu baris = satu siswa pada satu kelas + tanggal + jam.
     * Kuliah: dibaca oleh form input-jurnal (read-only) dan
     * detail jurnal (Orang 2).
     */
    public function up(): void
    {
        Schema::create('piket_kehadiran_siswa', function (Blueprint $table) {
            $table->id('id_piket_kehadiran');

            $table->unsignedInteger('id_kelas');
            $table->unsignedBigInteger('id_siswa');
            $table->date('tanggal');
            $table->unsignedInteger('jam_ke');

            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Tanpa Keterangan'])->default('Hadir');
            $table->string('keterangan', 255)->nullable();

            // Bisa dicatat oleh guru piket yang sedang bertugas.
            $table->unsignedInteger('id_guru_piket')->nullable();
            $table->string('sumber', 30)->default('Piket');

            $table->timestamps();

            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('id_siswa')->on('siswa')->cascadeOnDelete();
            $table->foreign('id_guru_piket')->references('id_pengguna')->on('pengguna')->nullOnDelete();

            $table->unique(['id_kelas', 'id_siswa', 'tanggal', 'jam_ke'], 'piket_kehadiran_siswa_unik');
            $table->index(['tanggal', 'id_kelas', 'jam_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_kehadiran_siswa');
    }
};
