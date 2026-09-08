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
    Schema::create('guru_piket', function (Blueprint $table) {
        $table->increments('id_guru_piket');

        $table->integer('id_pengguna');

        $table->enum('hari', [
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu'
        ]);

        $table->time('jam_mulai');
        $table->time('jam_selesai');

        $table->boolean('aktif')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_piket');
    }
};
