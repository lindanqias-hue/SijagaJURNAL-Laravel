<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ganti status lama menjadi status baru
        DB::table('kehadiran_gurus')
            ->where('status', 'Tanpa Keterangan')
            ->update([
                'status' => 'Tidak Hadir',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan status jika migration di-rollback
        DB::table('kehadiran_gurus')
            ->where('status', 'Tidak Hadir')
            ->update([
                'status' => 'Tanpa Keterangan',
            ]);
    }
};