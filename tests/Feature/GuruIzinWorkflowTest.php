<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\Jadwal;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GuruIzinWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sick_leave_is_validated_by_wakasek_and_visible_to_class_secretary(): void
    {
        $this->travelTo(Carbon::parse('2026-09-27 08:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'guru');
        $wakasek = $this->createPengguna('WAKASEK001', 'wakasek');

        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 1',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $sekretaris = $this->createPengguna('SEKRETARIS001', 'sekretaris', $idKelas);

        Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => 'Senin',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
        ]);

        /*
         * Pengajuan izin kini dibuat dari halaman terpisah (izin-guru)
         * dan disimpan pada tabel izin_guru, bukan pada jurnal.
         */
        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'role' => 'guru']);

        Livewire::test('izin-guru')
            ->set('jenisIzin', 'Sakit')
            ->set('tanggal', '2026-09-28')
            ->set('id_kelas', $idKelas)
            ->set('tugasPerJam.1', 'Kerjakan latihan bab 2 secara mandiri.')
            ->set('catatan', 'Sudah izin ke kepala sekolah.')
            ->call('simpan')
            ->assertHasNoErrors();

        $izin = IzinGuru::query()->sole();

        $this->assertDatabaseHas('izin_guru', [
            'id_izin' => $izin->id_izin,
            'id_guru' => $guru->id_pengguna,
            'jenis_izin' => 'Sakit',
            'status' => 'Menunggu',
        ]);

        /*
         * Wakasek memvalidasi pengajuan izin guru.
         */
        $this->withSession(['id_pengguna' => $wakasek->id_pengguna, 'role' => 'wakasek']);

        Livewire::test('wakasek')
            ->call('validasiPengajuanIzin', $izin->id_izin, 'Disetujui')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('izin_guru', [
            'id_izin' => $izin->id_izin,
            'status' => 'Disetujui',
            'id_validator' => $wakasek->id_pengguna,
        ]);

        /*
         * Kehadiran guru tersinkron dari izin yang disetujui.
         */
        $this->assertDatabaseHas('kehadiran_gurus', [
            'id_guru' => $guru->id_pengguna,
            'status' => 'Sakit',
        ]);
        $this->assertSame(
            '2026-09-28',
            Carbon::parse(DB::table('kehadiran_gurus')->where('status', 'Sakit')->value('tanggal'))->toDateString()
        );

        /*
         * Sekretaris kelas melihat informasi izin di halamannya.
         */
        $this->withSession([
            'id_pengguna' => $sekretaris->id_pengguna,
            'id_kelas' => $idKelas,
            'role' => 'sekretaris',
        ]);

        Livewire::test('sekretaris')
            ->set('activeSection', 'tugas-guru')
            ->assertSee('Sakit')
            ->assertSee('Kerjakan latihan bab 2 secara mandiri.');
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
