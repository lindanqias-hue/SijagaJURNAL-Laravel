<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            if (!Schema::hasColumn('dispensasi', 'id_wakasek')) {
                $table->unsignedBigInteger('id_wakasek')
                    ->nullable()
                    ->after('id_guru_piket');
            }

            if (!Schema::hasColumn('dispensasi', 'token')) {
                $table->string('token', 100)
                    ->nullable()
                    ->unique()
                    ->after('status');
            }

            if (!Schema::hasColumn('dispensasi', 'waktu_approval')) {
                $table->timestamp('waktu_approval')
                    ->nullable()
                    ->after('token');
            }

            if (!Schema::hasColumn('dispensasi', 'catatan_wakasek')) {
                $table->text('catatan_wakasek')
                    ->nullable()
                    ->after('waktu_approval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            if (Schema::hasColumn('dispensasi', 'token')) {
                $table->dropUnique(['token']);
                $table->dropColumn('token');
            }

            if (Schema::hasColumn('dispensasi', 'id_wakasek')) {
                $table->dropColumn('id_wakasek');
            }

            if (Schema::hasColumn('dispensasi', 'waktu_approval')) {
                $table->dropColumn('waktu_approval');
            }

            if (Schema::hasColumn('dispensasi', 'catatan_wakasek')) {
                $table->dropColumn('catatan_wakasek');
            }
        });
    }
};
