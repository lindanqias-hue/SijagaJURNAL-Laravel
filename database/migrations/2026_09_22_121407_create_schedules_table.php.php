<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bungkus pembuatan tabel kelas dengan pengecekan
        if (!Schema::hasTable('kelas')) {
            Schema::create('kelas', function (Blueprint $table) {
                $table->id();
                $table->string('nama_kelas');
                $table->string('tingkat');
                $table->string('jurusan');
                $table->timestamps();
            });
        }

        // Bungkus pembuatan tabel jadwal_pelajarans dengan pengecekan
        if (!Schema::hasTable('jadwal_pelajarans')) {
            Schema::create('jadwal_pelajarans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
                $table->string('hari');
                $table->string('tipe');
                $table->integer('jam_ke')->nullable();
                $table->string('jam_mulai');
                $table->string('jam_selesai');
                $table->string('mata_pelajaran');
                $table->string('guru')->nullable();
                $table->string('ruangan')->nullable();
                $table->timestamps();
            });
        }
    }
};
