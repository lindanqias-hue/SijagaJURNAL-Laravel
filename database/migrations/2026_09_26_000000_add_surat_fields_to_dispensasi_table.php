<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('dispensasi', 'jenis_surat')) {
                $table->string('jenis_surat', 20)->default('Dispensasi');
            }

            if (! Schema::hasColumn('dispensasi', 'lampiran_path')) {
                $table->string('lampiran_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('dispensasi', 'jenis_surat')) {
                $columns[] = 'jenis_surat';
            }

            if (Schema::hasColumn('dispensasi', 'lampiran_path')) {
                $columns[] = 'lampiran_path';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
