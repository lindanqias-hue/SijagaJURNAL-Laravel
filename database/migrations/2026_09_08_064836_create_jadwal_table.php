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
    Schema::create('jadwal', function (Blueprint $table) {
    $table->id('id_jadwal');

    $table->unsignedBigInteger('id_guru');
    $table->unsignedBigInteger('id_kelas');

    $table->enum('hari', [
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    ]);

    $table->integer('jam_ke');
    $table->time('jam_mulai');
    $table->time('jam_selesai');
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
