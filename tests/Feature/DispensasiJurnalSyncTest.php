<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Models\Pengguna;
use App\Services\DispensasiJurnalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DispensasiJurnalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_external_leave_syncs_as_izin_and_cannot_be_changed_to_alpa(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 08:00:00', 'Asia/Jakarta'));

        $guru = Pengguna::create([
            'nip' => 'GURU001',
            'nama' => 'Guru Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 2',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        $idSiswa = DB::table('siswa')->insertGetId([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Uji',
        ], 'id_siswa');
        $jurnal = Jurnal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'tanggal' => '2026-09-28',
            'jam_ke' => 1,
            'materi' => 'Materi uji',
        ]);
        $surat = Dispensasi::create([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'jenis_dispensasi' => 'Sehari Penuh',
            'jenis_surat' => Dispensasi::JENIS_SURAT_IZIN,
            'tanggal' => '2026-09-28',
            'alasan' => 'Surat izin orang tua',
            'id_guru_piket' => $guru->id_pengguna,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'token' => 'izin-token-test',
        ]);

        $service = app(DispensasiJurnalService::class);
        $service->sync($surat);

        $this->assertDatabaseHas('absensi_siswa', [
            'id_jurnal' => $jurnal->id_jurnal,
            'id_siswa' => $idSiswa,
            'keterangan' => 'Izin',
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'role' => 'guru']);

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelas)
            ->set('tanggal', '2026-09-28')
            ->set('jam_ke', 1)
            ->call('loadSiswa')
            ->call('setAbsensiSiswa', $idSiswa, 'Alpa')
            ->assertSet('absensi.'.$idSiswa, 'Izin');
    }
}
