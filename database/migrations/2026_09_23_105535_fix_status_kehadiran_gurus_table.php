<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kehadiran_gurus', function (Blueprint $table) {
            $table->string('status')->change();
        });
    }

    public function down(): void
    {
        Schema::table('kehadiran_gurus', function (Blueprint $table) {
            $table->enum('status', [
                'Menunggu',
                'Hadir',
                'Izin',
                'Sakit',
                'Tanpa Keterangan',
            ])->default('Menunggu')->change();
        });
    }
};