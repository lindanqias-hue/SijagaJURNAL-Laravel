<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->boolean('adalah_pengajuan_izin')->default(false)->after('status_kehadiran_guru');
            $table->string('jenis_izin', 100)->nullable()->after('adalah_pengajuan_izin');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropColumn(['adalah_pengajuan_izin', 'jenis_izin']);
        });
    }
};
