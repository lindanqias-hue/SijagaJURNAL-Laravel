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
    Schema::create('siswa', function (Blueprint $table) {
        $table->id('id_siswa');
        $table->unsignedInteger('id_kelas');
        $table->string('nama_siswa', 100);

        $table->foreign('id_kelas')
              ->references('id_kelas')
              ->on('kelas')
              ->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
