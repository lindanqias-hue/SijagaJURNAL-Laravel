<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
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

        $jadwal = Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => 'Senin',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'role' => 'guru']);

        Livewire::test('input-jurnal')
            ->call('toggleModeIzin')
            ->set('tanggal', '2026-09-28')
            ->set('id_kelas', $idKelas)
            ->set('jenisIzin', 'Sakit')
            ->set('materi', 'Kerjakan latihan bab 2 secara mandiri.')
            ->call('save')
            ->assertHasNoErrors();

        $jurnal = Jurnal::query()->sole();

        $this->assertDatabaseHas('jurnal', [
            'id_jurnal' => $jurnal->id_jurnal,
            'status_kehadiran_guru' => 'Sakit',
            'adalah_pengajuan_izin' => true,
            'jenis_izin' => 'Sakit',
            'materi' => 'Kerjakan latihan bab 2 secara mandiri.',
            'status_validasi' => 'Menunggu',
        ]);

        $this->withSession(['id_pengguna' => $wakasek->id_pengguna, 'role' => 'wakasek']);

        Livewire::test('wakasek')
            ->call('validasiPengajuanIzin', $jurnal->id_jurnal, 'Divalidasi')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jurnal', [
            'id_jurnal' => $jurnal->id_jurnal,
            'status_validasi' => 'Divalidasi',
            'id_validator' => $wakasek->id_pengguna,
        ]);
        $this->assertDatabaseHas('kehadiran_gurus', [
            'id_jadwal' => $jadwal->id_jadwal,
            'id_guru' => $guru->id_pengguna,
            'status' => 'Sakit',
            'catatan' => 'Sakit',
        ]);
        $this->assertSame(
            '2026-09-28',
            Carbon::parse(DB::table('kehadiran_gurus')->where('status', 'Sakit')->value('tanggal'))->toDateString()
        );

        $this->withSession([
            'id_pengguna' => $sekretaris->id_pengguna,
            'id_kelas' => $idKelas,
            'role' => 'sekretaris',
        ]);

        Livewire::test('sekretaris')
            ->set('activeSection', 'validasi-jurnal')
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
