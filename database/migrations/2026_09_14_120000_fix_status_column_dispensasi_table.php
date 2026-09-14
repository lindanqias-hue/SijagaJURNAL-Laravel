<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kolom `status` di tabel `dispensasi` masih terkunci ke CHECK constraint
     * lama ('Aktif', 'Selesai') dari migration 2026_09_09_010236, karena
     * migration 2026_09_11_114840 (yang seharusnya mendefinisikan ulang
     * status jadi alur approval) di-skip oleh guard hasTable() — tabelnya
     * sudah lebih dulu dibuat oleh migration 9 Sep.
     *
     * Migration ini:
     * 1. Mengubah kolom status jadi string biasa (tanpa CHECK) supaya tidak
     *    terjebak masalah yang sama lagi setiap kali menambah status baru.
     *    Validasi nilai yang diperbolehkan dipindah ke level aplikasi
     *    (lihat App\Models\Dispensasi::STATUSES).
     * 2. Menormalkan data lama: 'Aktif' -> 'Menunggu Persetujuan'.
     */
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->string('status', 30)
                ->default('Menunggu Persetujuan')
                ->change();
        });

        // Normalisasi baris lama yang sempat kesimpan dengan status enum lama.
        DB::table('dispensasi')
            ->where('status', 'Aktif')
            ->update(['status' => 'Menunggu Persetujuan']);
    }

    public function down(): void
    {
        DB::table('dispensasi')
            ->whereNotIn('status', ['Aktif', 'Selesai'])
            ->update(['status' => 'Aktif']);

        Schema::table('dispensasi', function (Blueprint $table) {
            $table->enum('status', ['Aktif', 'Selesai'])
                ->default('Aktif')
                ->change();
        });
    }
};
