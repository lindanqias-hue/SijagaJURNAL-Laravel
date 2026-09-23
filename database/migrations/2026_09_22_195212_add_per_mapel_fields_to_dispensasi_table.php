<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE dispensasi MODIFY jenis_dispensasi ENUM('Per Jam', 'Sehari Penuh', 'Per Mapel') NOT NULL"
        );

        Schema::table('dispensasi', function (Blueprint $table) {
            if (! Schema::hasColumn('dispensasi', 'id_jadwal')) {
                $table->unsignedBigInteger('id_jadwal')->nullable()->after('id_kelas');
            }

            if (! Schema::hasColumn('dispensasi', 'id_guru')) {
                $table->integer('id_guru')->nullable()->after('id_jadwal');
            }

            if (! Schema::hasColumn('dispensasi', 'mapel')) {
                $table->string('mapel', 100)->nullable()->after('id_guru');
            }

            $table->foreign('id_jadwal')
                ->references('id_jadwal')
                ->on('jadwal')
                ->nullOnDelete();

            $table->foreign('id_guru')
                ->references('id_pengguna')
                ->on('pengguna')
                ->nullOnDelete();

            $table->index(['tanggal', 'id_kelas', 'jenis_dispensasi']);
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi', function (Blueprint $table) {
            $table->dropForeign(['id_jadwal']);
            $table->dropForeign(['id_guru']);
            $table->dropIndex(['tanggal', 'id_kelas', 'jenis_dispensasi']);
            $table->dropColumn(['id_jadwal', 'id_guru', 'mapel']);
        });

        DB::statement(
            "ALTER TABLE dispensasi MODIFY jenis_dispensasi ENUM('Per Jam', 'Sehari Penuh') NOT NULL"
        );
    }
};
