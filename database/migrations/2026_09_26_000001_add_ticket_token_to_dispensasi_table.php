<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dispensasi', 'ticket_token')) {
            Schema::table('dispensasi', function (Blueprint $table): void {
                $table->string('ticket_token', 100)->nullable()->unique();
            });
        }

        DB::table('dispensasi')
            ->whereNull('ticket_token')
            ->orderBy('id_dispensasi')
            ->chunkById(100, function ($records): void {
                foreach ($records as $record) {
                    DB::table('dispensasi')
                        ->where('id_dispensasi', $record->id_dispensasi)
                        ->update(['ticket_token' => Str::random(64)]);
                }
            }, 'id_dispensasi');
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispensasi', 'ticket_token')) {
            Schema::table('dispensasi', function (Blueprint $table): void {
                $table->dropUnique(['ticket_token']);
                $table->dropColumn('ticket_token');
            });
        }
    }
};
