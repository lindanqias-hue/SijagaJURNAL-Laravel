<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalisasi istilah lama agar selalu sesuai enum kehadiran_gurus.
        DB::table('kehadiran_gurus')
            ->where('status', 'Tidak Hadir')
            ->update([
                'status' => 'Tanpa Keterangan',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak ada rollback data: "Tidak Hadir" bukan nilai status proyek.
    }
};
