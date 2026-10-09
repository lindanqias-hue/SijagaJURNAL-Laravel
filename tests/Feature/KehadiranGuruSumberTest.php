<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\KehadiranGuru;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KehadiranGuruSumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_sumber_kehadiran_yang_dipakai_aplikasi_tercatat(): void
    {
        // Aplikasi memakai beberapa sumber baru selain enum awal
        // (['Sistem', 'Guru Piket']): Piket, Wakasek, dan
        // "Sistem (Otomatis)" untuk proses akhir hari.
        $sumberBaru = [
            'Piket',
            'Wakasek',
            'Sistem (Otomatis)',
            'Sistem',
            'Guru Piket',
        ];

        // SQLite melaporkan 'varchar', koneksi lain melaporkan 'string'.
        // Keduanya berarti kolom sudah BUKAN enum lagi.
        $this->assertContains(
            Schema::getColumnType('kehadiran_gurus', 'sumber'),
            ['varchar', 'string']
        );

        $guru = Pengguna::create([
            'nip' => 'GURU200',
            'nama' => 'Guru Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);

        $jadwal = Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => DB::table('kelas')->insertGetId(['nama_kelas' => 'X SIM', 'jumlah_siswa' => 0], 'id_kelas'),
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
        ]);

        foreach ($sumberBaru as $index => $sumber) {
            $jadwalUji = Jadwal::create([
                'id_guru' => $guru->id_pengguna,
                'id_kelas' => $jadwal->id_kelas,
                'hari' => $jadwal->hari,
                'jam_ke' => $index + 2,
                'jam_mulai' => '07:40:00',
                'jam_selesai' => '08:20:00',
            ]);

            KehadiranGuru::create([
                'id_jadwal' => $jadwalUji->id_jadwal,
                'id_guru' => $guru->id_pengguna,
                'tanggal' => Carbon::now('Asia/Jakarta')->toDateString(),
                'status' => $index === 0 ? 'Dinas Luar Kota' : 'Hadir',
                'sumber' => $sumber,
            ]);
        }

        foreach ($sumberBaru as $sumber) {
            $this->assertDatabaseHas('kehadiran_gurus', ['sumber' => $sumber]);
        }
    }
}
