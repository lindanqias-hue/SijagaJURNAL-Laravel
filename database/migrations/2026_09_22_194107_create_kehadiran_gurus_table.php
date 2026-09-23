<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kehadiran_gurus')) {
            DB::statement(
                'ALTER TABLE kehadiran_gurus MODIFY id_guru INT NOT NULL'
            );

            Schema::table('kehadiran_gurus', function (Blueprint $table) {
                $table->foreign('id_guru')
                    ->references('id_pengguna')
                    ->on('pengguna')
                    ->cascadeOnDelete();

                $table->unique(['id_jadwal', 'tanggal']);
                $table->index(['id_guru', 'tanggal', 'status']);
            });

            return;
        }

        Schema::create('kehadiran_gurus', function (Blueprint $table) {
            $table->id('id_kehadiran_guru');
            $table->unsignedBigInteger('id_jadwal');
            $table->integer('id_guru');
            $table->date('tanggal');
            $table->enum('status', [
                'Menunggu',
                'Hadir',
                'Izin',
                'Sakit',
                'Tanpa Keterangan',
            ])->default('Menunggu');
            $table->enum('sumber', ['Sistem', 'Guru Piket'])->default('Sistem');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('id_jadwal')
                ->references('id_jadwal')
                ->on('jadwal')
                ->cascadeOnDelete();

            $table->foreign('id_guru')
                ->references('id_pengguna')
                ->on('pengguna')
                ->cascadeOnDelete();

            $table->unique(['id_jadwal', 'tanggal']);
            $table->index(['id_guru', 'tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kehadiran_gurus');
    }
};
