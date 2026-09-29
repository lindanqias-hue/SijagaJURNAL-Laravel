<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SuratDispensasiKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    public function test_letter_approved_after_its_class_hour_still_appears_for_the_secretary_and_can_be_downloaded(): void
    {
        // Dispensasi per jam 07:00-07:45, disetujui pukul 21:00 (di luar jendela).
        $this->travelTo(Carbon::parse('2026-09-30 07:00:00', 'Asia/Jakarta'));

        $fixture = $this->createFixture('Siswa Terlambat', 'Per Jam');
        $dispensasi = $fixture['dispensasi'];
        $dispensasi->forceFill([
            'status' => Dispensasi::STATUS_MENUNGGU,
            'id_wakasek' => $fixture['wakasek']->id_pengguna,
            'nomor_surat' => null,
        ])->save();

        $this->travelTo(Carbon::parse('2026-09-30 21:00:00', 'Asia/Jakarta'));

        $this->post(route('approve-dispensasi.setujui', [
            'token' => $dispensasi->token,
            'wakasek' => $fixture['wakasek']->id_pengguna,
        ]))->assertRedirect();

        $dispensasi->refresh();

        $this->assertSame(Dispensasi::STATUS_DISETUJUI, $dispensasi->status);
        $this->assertSame(
            'DIS-20260930-'.str_pad((string) $dispensasi->id_dispensasi, 5, '0', STR_PAD_LEFT),
            $dispensasi->nomor_surat,
            'Nomor surat harus dibuat saat wakasek menyetujui, bukan saat diunduh.'
        );

        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $fixture['idKelas'],
        ]);

        Livewire::test('sekretaris')
            ->set('activeSection', 'surat-dispensasi')
            ->assertSee('Siswa Terlambat')
            ->assertSee('Kedaluwarsa')
            ->assertSee('DIS-20260930-'.str_pad((string) $dispensasi->id_dispensasi, 5, '0', STR_PAD_LEFT));

        $this->get(route('surat-dispensasi.lihat', $dispensasi->id_dispensasi))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get(route('surat-dispensasi.unduh', $dispensasi->id_dispensasi))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_secretary_can_switch_to_another_date_to_find_archived_letters(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 09:00:00', 'Asia/Jakarta'));

        $fixture = $this->createFixture('Siswa Kemarin', 'Sehari Penuh', '2026-09-29');
        $idKelas = $fixture['idKelas'];

        $this->withSession([
            'id_pengguna' => $fixture['sekretaris']->id_pengguna,
            'role' => 'sekretaris',
            'id_kelas' => $idKelas,
        ]);

        Livewire::test('sekretaris')
            ->set('activeSection', 'surat-dispensasi')
            ->assertDontSee('Siswa Kemarin')
            ->set('tanggalSurat', '2026-09-29')
            ->assertSee('Siswa Kemarin')
            ->assertSee('Kedaluwarsa');
    }

    public function test_public_ticket_still_expires_outside_the_class_hour(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 07:00:00', 'Asia/Jakarta'));

        $fixture = $this->createFixture('Siswa Tiket', 'Per Jam');
        $dispensasi = $fixture['dispensasi'];

        $this->get(route('surat-dispensasi.ticket', [
            'id' => $dispensasi->id_dispensasi,
            'ticketToken' => $dispensasi->ticket_token,
        ]))->assertOk();

        $this->travelTo(Carbon::parse('2026-09-30 12:00:00', 'Asia/Jakarta'));

        $this->get(route('surat-dispensasi.ticket', [
            'id' => $dispensasi->id_dispensasi,
            'ticketToken' => $dispensasi->ticket_token,
        ]))->assertStatus(410);
    }

    private function createFixture(string $namaSiswa, string $jenisDispensasi, ?string $tanggal = null): array
    {
        $guru = $this->createPengguna('GURU-'.uniqid(), 'guru');
        $wakasek = $this->createPengguna('WAKA-'.uniqid(), 'wakasek');
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL '.uniqid(),
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => $namaSiswa,
        ], 'id_siswa');
        $sekretaris = $this->createPengguna('SEK-'.uniqid(), 'sekretaris', $idKelas);

        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => $jenisDispensasi,
            'jenis_surat' => Dispensasi::JENIS_SURAT_DISPENSASI,
            'tanggal' => $tanggal ?? '2026-09-30',
            'jam_ke' => $jenisDispensasi === 'Per Jam' ? 1 : null,
            'jam_ke_mulai' => $jenisDispensasi === 'Per Jam' ? 1 : null,
            'jam_ke_selesai' => $jenisDispensasi === 'Per Jam' ? 1 : null,
            'jam_mulai' => $jenisDispensasi === 'Per Jam' ? '07:00:00' : null,
            'jam_selesai' => $jenisDispensasi === 'Per Jam' ? '07:45:00' : null,
            'alasan' => 'Keperluan siswa',
            'id_guru_piket' => $guru->id_pengguna,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'id_wakasek' => $wakasek->id_pengguna,
            'token' => 'token-'.uniqid(),
            'ticket_token' => 'ticket-'.uniqid(),
        ]);

        return compact('dispensasi', 'guru', 'wakasek', 'sekretaris', 'idKelas');
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
