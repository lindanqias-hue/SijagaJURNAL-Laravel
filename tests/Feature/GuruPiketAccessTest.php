<?php

namespace Tests\Feature;

use App\Models\GuruPiket;
use App\Models\JadwalPiket;
use App\Models\Pengguna;
use App\Services\GuruPiketAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruPiketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_is_granted_for_weekly_or_date_specific_assignments_only(): void
    {
        $guruMingguan = $this->createGuru('GURU001');
        $guruTanggal = $this->createGuru('GURU002');
        $guruLain = $this->createGuru('GURU003');
        $tanggal = Carbon::parse('2026-09-28', 'Asia/Jakarta');

        GuruPiket::create([
            'id_pengguna' => $guruMingguan->id_pengguna,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'aktif' => true,
        ]);
        JadwalPiket::create([
            'id_guru' => $guruTanggal->id_pengguna,
            'tanggal' => $tanggal->toDateString(),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'status' => 'Aktif',
        ]);

        $access = app(GuruPiketAccessService::class);

        $this->assertTrue($access->bertugasPadaTanggal($guruMingguan->id_pengguna, $tanggal));
        $this->assertTrue($access->bertugasPadaTanggal($guruTanggal->id_pengguna, $tanggal));
        $this->assertFalse($access->bertugasPadaTanggal($guruLain->id_pengguna, $tanggal));
    }

    private function createGuru(string $nip): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nip,
            'password' => 'password',
            'role' => 'guru',
        ]);
    }
}
