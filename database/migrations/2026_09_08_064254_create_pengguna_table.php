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
    Schema::create('pengguna', function (Blueprint $table) {
        $table->increments('id_pengguna');

        $table->string('nip', 30)->unique();
        $table->string('nama', 100);
        $table->string('mapel_diampu', 100)->nullable();
        $table->string('no_hp', 20)->nullable();

        $table->enum('status_kepegawaian', [
            'PNS',
            'PPPK',
            'Honorer'
        ])->nullable();

        $table->string('password', 100);

        $table->enum('role', [
            'guru',
            'sekretaris',
            'admin',
            'guru_piket'
        ])->default('guru');

        $table->integer('id_kelas')->nullable();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
