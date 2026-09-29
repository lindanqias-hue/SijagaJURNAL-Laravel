<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('dispensasi', 'nomor_surat')) {
                $table->string('nomor_surat', 40)->nullable()->unique();
            }
        });

        Schema::create('dispensasi_unduhan', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_dispensasi');
            $table->unsignedInteger('id_pengguna');
            $table->timestamp('diunduh_pada');
            $table->timestamps();
            $table->foreign('id_dispensasi')->references('id_dispensasi')->on('dispensasi')->cascadeOnDelete();
            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->cascadeOnDelete();
            $table->index(['id_dispensasi', 'diunduh_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi_unduhan');

        Schema::table('dispensasi', function (Blueprint $table): void {
            if (Schema::hasColumn('dispensasi', 'nomor_surat')) {
                $table->dropUnique(['nomor_surat']);
                $table->dropColumn('nomor_surat');
            }
        });
    }
};
