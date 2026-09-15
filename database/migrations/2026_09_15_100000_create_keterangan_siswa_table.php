<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ini menyimpan catatan/keterangan bebas untuk absensi siswa
     * berstatus Sakit, Izin, atau Dispensasi (sebelumnya hanya Dispensasi
     * yang punya keterangan, dan itu pun nyangkut di kolom
     * absensi_siswa.keterangan_dispensasi).
     *
     * Dipisah ke tabel sendiri (bukan nambah kolom lagi di absensi_siswa)
     * supaya:
     * - id_absensi tetap 1-1 dengan absensi_siswa, ikut kehapus otomatis
     *   (cascade) kalau jurnal-nya diedit/dihapus.
     * - nama_siswa & kelas disimpan sebagai snapshot (sesuai keinginan),
     *   jadi histori keterangan tidak berubah walau siswa pindah kelas.
     */
    public function up(): void
    {
        Schema::create('keterangan_siswa', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_absensi')->unique();
            $table->unsignedBigInteger('id_siswa');

            // Snapshot data siswa saat keterangan dibuat
            $table->string('nama_siswa');
            $table->string('kelas');

            $table->enum('status', ['Sakit', 'Izin', 'Dispensasi']);
            $table->text('keterangan');
            $table->date('tanggal');

            $table->timestamps();

            $table->foreign('id_absensi')
                ->references('id_absensi')
                ->on('absensi_siswa')
                ->cascadeOnDelete();

            $table->foreign('id_siswa')
                ->references('id_siswa')
                ->on('siswa')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keterangan_siswa');
    }
};
