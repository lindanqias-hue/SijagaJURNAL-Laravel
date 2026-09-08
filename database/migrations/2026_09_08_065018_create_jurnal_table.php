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

        $table->integer('id_guru');
        $table->integer('id_kelas');

        $table->date('tanggal');
        $table->integer('jam_ke');
        $table->string('materi', 255);

        $table->integer('jumlah_hadir')->nullable();
        $table->integer('jumlah_tidak_hadir')->nullable();

        $table->enum('status_kehadiran_guru', [
            'Hadir',
            'Izin',
            'Sakit',
            'Tanpa Keterangan'
        ])->default('Hadir');

        $table->text('catatan')->nullable();

        $table->enum('status_validasi', [
            'Menunggu',
            'Divalidasi',
            'Ditolak'
        ])->default('Menunggu');

        $table->integer('id_validator')->nullable();
        $table->dateTime('tanggal_validasi')->nullable();
        $table->text('catatan_validasi')->nullable();
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
