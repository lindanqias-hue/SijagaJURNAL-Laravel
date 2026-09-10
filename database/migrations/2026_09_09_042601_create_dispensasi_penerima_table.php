<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensasi_penerima', function (Blueprint $table) {
    $table->id('id_penerima');

    $table->unsignedInteger('id_dispensasi');

    $table->integer('id_guru');

    $table->timestamp('dibaca_at')->nullable();

    $table->timestamps();

    $table->foreign('id_dispensasi')
        ->references('id_dispensasi')
        ->on('dispensasi')
        ->onDelete('cascade');

    $table->foreign('id_guru')
        ->references('id_pengguna')
        ->on('pengguna')
        ->onDelete('cascade');

    $table->unique([
        'id_dispensasi',
        'id_guru'
    ]);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi_penerima');
    }
};