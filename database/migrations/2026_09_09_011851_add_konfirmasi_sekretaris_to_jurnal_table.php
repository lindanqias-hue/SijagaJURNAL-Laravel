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
        Schema::table('jurnal', function (Blueprint $table) {
            // Status konfirmasi oleh sekretaris kelas — terpisah dari
            // status_validasi milik guru piket, karena tujuannya berbeda:
            // sekretaris hanya mengonfirmasi apakah guru benar-benar hadir
            // secara langsung di kelas.
            $table->enum('status_konfirmasi_sekretaris', [
                'Menunggu',
                'Sesuai',
                'Tidak Sesuai',
            ])->default('Menunggu')->after('status_validasi');

            $table->integer('id_sekretaris')->nullable()->after('status_konfirmasi_sekretaris');
            $table->dateTime('waktu_konfirmasi_sekretaris')->nullable()->after('id_sekretaris');
            $table->text('catatan_sekretaris')->nullable()->after('waktu_konfirmasi_sekretaris');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropColumn([
                'status_konfirmasi_sekretaris',
                'id_sekretaris',
                'waktu_konfirmasi_sekretaris',
                'catatan_sekretaris',
            ]);
        });
    }
};