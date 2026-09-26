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
        Schema::create('jurnal', function (Blueprint $table) {
            $table->increments('id_jurnal');

            // Relasi guru dan kelas
            $table->unsignedInteger('id_guru');
            $table->unsignedInteger('id_kelas');

            // Informasi jurnal
            $table->date('tanggal');
            $table->unsignedInteger('jam_ke');
            $table->string('materi', 255);

            // Kehadiran siswa
            $table->unsignedInteger('jumlah_hadir')->default(0);
            $table->unsignedInteger('jumlah_tidak_hadir')->default(0);

            // Kehadiran guru
            $table->enum('status_kehadiran_guru', [
                'Hadir',
                'Izin',
                'Sakit',
                'Tanpa Keterangan'
            ])->default('Hadir');

            $table->text('catatan')->nullable();

            // Validasi jurnal
            $table->enum('status_validasi', [
                'Menunggu',
                'Divalidasi',
                'Ditolak'
            ])->default('Menunggu');

            $table->unsignedInteger('id_validator')->nullable();
            $table->dateTime('tanggal_validasi')->nullable();
            $table->text('catatan_validasi')->nullable();

            /*
             * Mencegah jurnal duplikat.
             *
             * Satu guru tidak boleh mempunyai
             * lebih dari satu jurnal untuk:
             * guru + kelas + tanggal + jam
             */
            $table->unique(
                [
                    'id_guru',
                    'id_kelas',
                    'tanggal',
                    'jam_ke'
                ],
                'jurnal_guru_kelas_tanggal_jam_unique'
            );

            // Foreign key guru
            $table->foreign('id_guru')
                ->references('id_pengguna')
                ->on('pengguna')
                ->cascadeOnDelete();

            // Foreign key kelas
            $table->foreign('id_kelas')
                ->references('id_kelas')
                ->on('kelas')
                ->cascadeOnDelete();

            // Foreign key validator
            $table->foreign('id_validator')
                ->references('id_pengguna')
                ->on('pengguna')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};