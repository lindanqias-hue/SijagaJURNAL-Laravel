<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Kelas
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas'); // contoh: X RPL 1, XI TKI 2
            $table->string('tingkat');    // X, XI, XII
            $table->string('jurusan');    // RPL, TKI, TKJ, dll.
            $table->timestamps();
        });

        // Tabel Jadwal Pelajaran
        Schema::create('jadwal_pelajarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->string('hari');        // senin, selasa, dst.
            $table->string('tipe');        // KBM atau ISTIRAHAT / APEL
            $table->integer('jam_ke')->nullable(); // 1, 2, 3, dst.
            $table->string('jam_mulai');   // 07.00
            $table->string('jam_selesai'); // 07.40
            $table->string('mata_pelajaran');
            $table->string('guru')->nullable();
            $table->string('ruangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajarans');
        Schema::dropIfExists('kelas');
    }
};
