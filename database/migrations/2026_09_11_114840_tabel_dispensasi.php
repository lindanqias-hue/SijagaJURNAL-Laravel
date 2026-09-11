<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dispensasi')) {
            Schema::create('dispensasi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_siswa');
                $table->unsignedBigInteger('id_guru_piket');
                $table->unsignedBigInteger('id_wakasek')->nullable();
                $table->text('keterangan');
                $table->enum('status', ['Menunggu Persetujuan', 'Disetujui', 'Ditolak', 'Selesai'])->default('Menunggu Persetujuan');
                $table->string('token')->nullable();
                $table->timestamp('waktu_approval')->nullable();
                $table->text('catatan_wakasek')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi');
    }
};
