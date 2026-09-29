<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuratDispensasiDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_download_approved_letter_for_their_own_class_and_download_is_logged(): void
    {
        $fixture = $this->createApprovedDispensasi();
        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $fixture['idKelas'],
        ])->get(route('surat-dispensasi.unduh', $fixture['dispensasi']->id_dispensasi))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('dispensasi', [
            'id_dispensasi' => $fixture['dispensasi']->id_dispensasi,
            'nomor_surat' => 'DIS-'.now('Asia/Jakarta')->format('Ymd').'-'.str_pad((string) $fixture['dispensasi']->id_dispensasi, 5, '0', STR_PAD_LEFT),
        ]);
        $this->assertDatabaseHas('dispensasi_unduhan', [
            'id_dispensasi' => $fixture['dispensasi']->id_dispensasi,
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'aksi' => 'unduh',
        ]);
    }

    public function test_secretary_can_view_letter_and_view_is_logged_as_a_separate_action(): void
    {
        $fixture = $this->createApprovedDispensasi();

        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $fixture['idKelas'],
        ])->get(route('surat-dispensasi.lihat', $fixture['dispensasi']->id_dispensasi))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('dispensasi_unduhan', [
            'id_dispensasi' => $fixture['dispensasi']->id_dispensasi,
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'aksi' => 'lihat',
        ]);
    }

    public function test_secretary_cannot_download_a_letter_from_another_class(): void
    {
        $fixture = $this->createApprovedDispensasi();
        $idKelasLain = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL Lain',
            'jumlah_siswa' => 0,
        ], 'id_kelas');

        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $idKelasLain,
        ])->get(route('surat-dispensasi.unduh', $fixture['dispensasi']->id_dispensasi))
            ->assertNotFound();

        $this->assertDatabaseCount('dispensasi_unduhan', 0);
    }

    private function createApprovedDispensasi(): array
    {
        $guru = $this->createPengguna('GURU-DL-01', 'guru');
        $wakasek = $this->createPengguna('WAKA-DL-01', 'wakasek');
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL Test',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Test',
        ], 'id_siswa');
        $sekretaris = $this->createPengguna('SEK-DL-01', 'sekretaris', $idKelas);
        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'jenis_surat' => 'Dispensasi',
            'tanggal' => now('Asia/Jakarta')->toDateString(),
            'alasan' => 'Kegiatan sekolah',
            'id_guru_piket' => $guru->id_pengguna,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'id_wakasek' => $wakasek->id_pengguna,
            'ticket_token' => 'ticket-download-test',
        ]);

        return compact('dispensasi', 'sekretaris', 'idKelas');
    }

    private function createPengguna(string $nip, string $role, ?int $idKelas = null): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nip,
            'password' => 'password',
            'role' => $role,
            'id_kelas' => $idKelas,
        ]);
    }
}
