<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru_piket', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('hari');
            $table->index(['id_pengguna', 'tanggal', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::table('guru_piket', function (Blueprint $table) {
            $table->dropIndex(['id_pengguna', 'tanggal', 'aktif']);
            $table->dropColumn('tanggal');
        });
    }
};
