<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->unsignedBigInteger('id_wakasek')->nullable()->after('id_guru_piket');
            $table->string('token')->nullable()->unique()->after('status');
            $table->timestamp('waktu_approval')->nullable()->after('token');
            $table->text('catatan_wakasek')->nullable()->after('waktu_approval');
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->dropColumn([
                'id_wakasek',
                'token',
                'waktu_approval',
                'catatan_wakasek',
            ]);
        });
    }
};