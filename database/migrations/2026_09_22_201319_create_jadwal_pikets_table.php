<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_piket', function (Blueprint $table) {
            $table->increments('id_jadwal_piket');
            $table->integer('id_guru');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('status', 20)->default('Aktif');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_guru')
                ->references('id_pengguna')
                ->on('pengguna')
                ->cascadeOnDelete();

            $table->index(['id_guru', 'tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_piket');
    }
};
