<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalDispensasiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_assigned_wakasek_can_approve_a_dispensasi(): void
    {
        $guruPiket = $this->createPengguna('GURU001', 'guru');
        $wakasekDitunjuk = $this->createPengguna('WAKASEK001', 'wakasek');
        $wakasekLain = $this->createPengguna('WAKASEK002', 'wakasek');

        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 2',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Uji',
        ], 'id_siswa');

        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'tanggal' => '2026-09-26',
            'alasan' => 'Kegiatan sekolah',
            'id_guru_piket' => $guruPiket->id_pengguna,
            'status' => Dispensasi::STATUS_MENUNGGU,
            'id_wakasek' => $wakasekDitunjuk->id_pengguna,
            'token' => 'approval-token-test',
        ]);

        $this->post(route('approve-dispensasi.setujui', [
            'token' => $dispensasi->token,
            'wakasek' => $wakasekLain->id_pengguna,
        ]))->assertRedirect();

        $this->assertDatabaseHas('dispensasi', [
            'id_dispensasi' => $dispensasi->id_dispensasi,
            'status' => Dispensasi::STATUS_MENUNGGU,
            'id_wakasek' => $wakasekDitunjuk->id_pengguna,
        ]);
    }

    public function test_rejection_requires_a_reason_and_keeps_the_request_pending(): void
    {
        $guruPiket = $this->createPengguna('GURU003', 'guru');
        $wakasek = $this->createPengguna('WAKASEK003', 'wakasek');
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 2',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Uji',
        ], 'id_siswa');
        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'tanggal' => '2026-09-26',
            'alasan' => 'Kegiatan sekolah',
            'id_guru_piket' => $guruPiket->id_pengguna,
            'status' => Dispensasi::STATUS_MENUNGGU,
            'id_wakasek' => $wakasek->id_pengguna,
            'token' => 'approval-rejection-test',
        ]);

        $this->from(route('approve-dispensasi', [
            'token' => $dispensasi->token,
            'wakasek' => $wakasek->id_pengguna,
        ]))->post(route('approve-dispensasi.tolak', [
            'token' => $dispensasi->token,
            'wakasek' => $wakasek->id_pengguna,
        ]))->assertSessionHasErrors('catatan_wakasek');

        $this->assertDatabaseHas('dispensasi', [
            'id_dispensasi' => $dispensasi->id_dispensasi,
            'status' => Dispensasi::STATUS_MENUNGGU,
        ]);
    }

    public function test_approved_ticket_requires_its_separate_ticket_token(): void
    {
        $guruPiket = $this->createPengguna('GURU004', 'guru');
        $wakasek = $this->createPengguna('WAKASEK004', 'wakasek');
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 2',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Uji',
        ], 'id_siswa');
        $dispensasi = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'tanggal' => '2026-09-26',
            'alasan' => 'Kegiatan sekolah',
            'id_guru_piket' => $guruPiket->id_pengguna,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'id_wakasek' => $wakasek->id_pengguna,
            'token' => 'approval-only-token',
            'ticket_token' => 'ticket-only-token',
        ]);

        $this->get(route('surat-dispensasi.ticket', [
            'id' => $dispensasi->id_dispensasi,
            'ticketToken' => $dispensasi->ticket_token,
        ]))->assertOk()->assertSee('QR unik tiket dispensasi');

        $this->get(route('surat-dispensasi.ticket', [
            'id' => $dispensasi->id_dispensasi,
            'ticketToken' => $dispensasi->token,
        ]))->assertNotFound();
    }

    private function createPengguna(string $nip, string $role): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nip,
            'password' => 'password',
            'role' => $role,
        ]);
    }
}
