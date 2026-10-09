<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin_guru', function (Blueprint $table) {
            $table->increments('id_izin');
            $table->unsignedInteger('id_guru');
            $table->unsignedInteger('id_kelas');
            $table->date('tanggal');
            $table->unsignedInteger('jam_ke_mulai');
            $table->unsignedInteger('jam_ke_selesai');
            $table->string('jenis_izin', 50); // Izin, Sakit, Dinas Luar Kota, Cuti
            $table->json('tugas_per_jam')->nullable(); // [jam_ke => tugas]
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedInteger('id_validator')->nullable();
            $table->dateTime('tanggal_validasi')->nullable();
            $table->text('catatan_validasi')->nullable();
            $table->timestamps();

            $table->foreign('id_guru')->references('id_pengguna')->on('pengguna')->cascadeOnDelete();
            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->cascadeOnDelete();
            $table->foreign('id_validator')->references('id_pengguna')->on('pengguna')->nullOnDelete();

            $table->index(['id_guru', 'tanggal', 'status']);
            $table->index(['id_kelas', 'tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_guru');
    }
};
