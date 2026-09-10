<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->integer('jam_ke_mulai')
                ->nullable()
                ->after('jam_ke');

            $table->integer('jam_ke_selesai')
                ->nullable()
                ->after('jam_ke_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->dropColumn([
                'jam_ke_mulai',
                'jam_ke_selesai',
            ]);
        });
    }
};