<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_siswa', function (Blueprint $table) {
            $table->id('id_absensi');

            $table->integer('id_jurnal');
            $table->unsignedBigInteger('id_siswa');

            $table->enum('keterangan', [
                'Hadir',
                'Izin',
                'Sakit',
                'Alpa'
            ])->default('Hadir');

            $table->timestamps();

            $table->foreign('id_jurnal')
                ->references('id_jurnal')
                ->on('jurnal')
                ->cascadeOnDelete();

            $table->foreign('id_siswa')
                ->references('id_siswa')
                ->on('siswa')
                ->cascadeOnDelete();

            $table->unique(['id_jurnal', 'id_siswa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_siswa');
    }
};