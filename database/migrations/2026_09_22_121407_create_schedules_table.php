<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_kelas');
            $table->string('hari');
            $table->string('tipe');
            $table->unsignedTinyInteger('jam_ke')->nullable();
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('mata_pelajaran');
            $table->string('guru')->nullable();
            $table->string('ruangan')->nullable();
            $table->timestamps();

            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
