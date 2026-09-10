<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensasi', function (Blueprint $table) {

            $table->increments('id_dispensasi');

            // Siswa yang mendapat dispensasi
            $table->unsignedBigInteger('id_siswa');

            // Kelas siswa
            $table->integer('id_kelas');

            // Jenis dispensasi
            $table->enum('jenis_dispensasi', [
                'Per Jam',
                'Sehari Penuh'
            ]);

            // Otomatis diisi sistem
            $table->date('tanggal');

            // Diisi otomatis jika jenis = Per Jam
            $table->integer('jam_ke')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();

            // Alasan dispensasi
            $table->text('alasan');

            // Guru piket yang membuat dispensasi
            $table->integer('id_guru_piket');

            // Status dispensasi
            $table->enum('status', [
                'Aktif',
                'Selesai'
            ])->default('Aktif');

            $table->timestamps();

            // Relasi siswa
            $table->foreign('id_siswa')
                ->references('id_siswa')
                ->on('siswa')
                ->onDelete('cascade');

            // Relasi kelas
            $table->foreign('id_kelas')
                ->references('id_kelas')
                ->on('kelas')
                ->onDelete('cascade');

            // Relasi guru piket
            $table->foreign('id_guru_piket')
                ->references('id_pengguna')
                ->on('pengguna')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi');
    }
};