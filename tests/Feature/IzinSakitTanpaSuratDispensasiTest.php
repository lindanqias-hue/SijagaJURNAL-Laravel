<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IzinSakitTanpaSuratDispensasiTest extends TestCase
{
    use RefreshDatabase;

    public static function jenisTanpaSurat(): array
    {
        return [
            'izin' => ['Izin'],
            'sakit' => ['Sakit'],
        ];
    }

    #[DataProvider('jenisTanpaSurat')]
    public function test_secretary_cannot_download_or_view_izin_or_sakit_letters(string $jenisSurat): void
    {
        $fixture = $this->createDispensasi($jenisSurat, 'Siswa Tanpa Surat');

        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $fixture['idKelas'],
        ]);

        $this->get(route('surat-dispensasi.unduh', $fixture['dispensasi']->id_dispensasi))
            ->assertNotFound();
        $this->get(route('surat-dispensasi.lihat', $fixture['dispensasi']->id_dispensasi))
            ->assertNotFound();

        $this->assertDatabaseCount('dispensasi_unduhan', 0);
    }

    #[DataProvider('jenisTanpaSurat')]
    public function test_public_ticket_route_rejects_izin_and_sakit(string $jenisSurat): void
    {
        $fixture = $this->createDispensasi($jenisSurat, 'Siswa Tanpa Surat');

        $this->get(route('surat-dispensasi.ticket', [
            'id' => $fixture['dispensasi']->id_dispensasi,
            'ticketToken' => $fixture['dispensasi']->ticket_token,
        ]))->assertNotFound();
    }

    #[DataProvider('jenisTanpaSurat')]
    public function test_authenticated_detail_route_rejects_izin_and_sakit(string $jenisSurat): void
    {
        $fixture = $this->createDispensasi($jenisSurat, 'Siswa Tanpa Surat');

        $this->withSession([
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'role' => 'guru',
        ])->get(route('surat-dispensasi.detail', $fixture['dispensasi']->id_dispensasi))
            ->assertNotFound();
    }

    public function test_secretary_surat_menu_only_lists_dispensasi_letters(): void
    {
        $dispensasi = $this->createDispensasi(Dispensasi::JENIS_SURAT_DISPENSASI, 'Siswa Dispensasi');
        $izin = $this->createDispensasi(
            Dispensasi::JENIS_SURAT_IZIN,
            'Siswa Izin Tanpa Surat',
            null,
            $dispensasi['idKelas'],
            $dispensasi['sekretaris']
        );

        $this->withSession([
            'id_pengguna' => $dispensasi['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $dispensasi['idKelas'],
        ]);

        Livewire::test('sekretaris')
            ->set('activeSection', 'surat-dispensasi')
            ->assertSee('Siswa Dispensasi')
            ->assertDontSee('Siswa Izin Tanpa Surat');

        $this->assertSame(Dispensasi::JENIS_SURAT_DISPENSASI, $dispensasi['dispensasi']->jenis_surat);
        $this->assertSame(Dispensasi::JENIS_SURAT_IZIN, $izin['dispensasi']->jenis_surat);
    }

    public function test_teacher_notification_for_izin_has_no_letter_link(): void
    {
        $guru = $this->createPengguna('GURU-'.uniqid(), 'guru');
        $izin = $this->createDispensasi(Dispensasi::JENIS_SURAT_IZIN, 'Siswa Izin Tanpa Surat', $guru);
        $dispensasi = $this->createDispensasi(Dispensasi::JENIS_SURAT_DISPENSASI, 'Siswa Dispensasi', $guru);

        foreach ([$izin, $dispensasi] as $fixture) {
            DB::table('dispensasi_penerima')->insert([
                'id_dispensasi' => $fixture['dispensasi']->id_dispensasi,
                'id_guru' => $fixture['guru']->id_pengguna,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->withSession([
            'id_pengguna' => $izin['guru']->id_pengguna,
            'role' => 'guru',
        ]);

        $notifikasi = Livewire::test('notifikasi')
            ->assertSee('Siswa Izin')
            ->assertSee('Siswa Dispensasi');

        $html = $notifikasi->html();

        $this->assertStringNotContainsString(
            route('surat-dispensasi.detail', $izin['dispensasi']->id_dispensasi),
            $html
        );
        $this->assertStringContainsString(
            route('surat-dispensasi.detail', $dispensasi['dispensasi']->id_dispensasi),
            $html
        );
    }

    private function createDispensasi(
        string $jenisSurat,
        string $namaSiswa,
        ?Pengguna $guru = null,
        ?int $idKelas = null,
        ?Pengguna $sekretaris = null
    ): array {
        $guru ??= $this->createPengguna('GURU-'.uniqid(), 'guru');
        $wakasek = $this->createPengguna('WAKA-'.uniqid(), 'wakasek');
        $idKelas ??= DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL '.uniqid(),
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => $namaSiswa,
        ], 'id_siswa');
        $sekretaris ??= $this->createPengguna('SEK-'.uniqid(), 'sekretaris', $idKelas);

        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'jenis_surat' => $jenisSurat,
            'tanggal' => now('Asia/Jakarta')->toDateString(),
            'alasan' => 'Keperluan siswa',
            'id_guru_piket' => $guru->id_pengguna,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'id_wakasek' => $jenisSurat === Dispensasi::JENIS_SURAT_DISPENSASI ? $wakasek->id_pengguna : null,
            'token' => 'token-'.uniqid(),
            'ticket_token' => 'ticket-'.uniqid(),
        ]);

        return compact('dispensasi', 'guru', 'sekretaris', 'idKelas');
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
