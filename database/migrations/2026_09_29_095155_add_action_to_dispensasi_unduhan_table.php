<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi_unduhan', function (Blueprint $table): void {
            $table->string('aksi', 20)->default('unduh')->after('id_pengguna');
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi_unduhan', function (Blueprint $table): void {
            $table->dropColumn('aksi');
        });
    }
};
