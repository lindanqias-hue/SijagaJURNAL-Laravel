<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Pengguna;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InputJurnalMultiJamTest extends TestCase
{
    use RefreshDatabase;

    public function test_satu_input_menyimpan_jurnal_untuk_seluruh_jam_otomatis(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 07:10:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001');
        $idKelas = $this->createKelasSiswa();

        $this->createJadwalRambung($guru, $idKelas, 1, '07:00:00', '07:45:00');
        $this->createJadwalRambung($guru, $idKelas, 2, '07:45:00', '08:30:00');
        $this->createJadwalRambung($guru, $idKelas, 3, '08:30:00', '09:15:00');

        $this->withSession([
            'id_pengguna' => $guru->id_pengguna,
            'role' => 'guru',
        ]);

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelas)
            ->assertSet('jamTerpilih', [1, 2, 3])
            ->set('materi', 'Persamaan linear')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            [1, 2, 3],
            Jurnal::query()->orderBy('jam_ke')->pluck('jam_ke')->all()
        );

        $this->assertDatabaseHas('jurnal', [
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'jam_ke' => 3,
            'materi' => 'Persamaan linear',
            'jumlah_hadir' => 2,
            'jumlah_tidak_hadir' => 0,
            'status_validasi' => 'Menunggu',
        ]);

        $jurnal = Jurnal::query()->where('jam_ke', 2)->firstOrFail();

        $this->assertSame(
            2,
            AbsensiSiswa::query()
                ->where('id_jurnal', $jurnal->id_jurnal)
                ->count()
        );
    }

    public function test_jam_otomatis_melompat_ke_rentang_berikutnya_yang_kosong(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 07:10:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001');
        $idKelas = $this->createKelasSiswa();

        $this->createJadwalRambung($guru, $idKelas, 1, '07:00:00', '07:45:00');
        $this->createJadwalRambung($guru, $idKelas, 2, '07:45:00', '08:30:00');
        $this->createJadwalRambung($guru, $idKelas, 3, '09:00:00', '09:40:00');

        $this->withSession([
            'id_pengguna' => $guru->id_pengguna,
            'role' => 'guru',
        ]);

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelas)
            ->assertSet('jamTerpilih', [1, 2])
            ->set('materi', 'Persamaan linear')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('jamTerpilih', [3])
            ->set('materi', 'Persamaan kuadrat')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('jamTerpilih', []);

        $this->assertSame(
            [1, 2, 3],
            Jurnal::query()->orderBy('jam_ke')->pluck('jam_ke')->all()
        );
    }

    public function test_jam_otomatis_mengosongkan_form_saat_semua_jam_terisi(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 07:10:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001');
        $idKelas = $this->createKelasSiswa();

        $this->createJadwalRambung($guru, $idKelas, 1, '07:00:00', '07:45:00');
        $this->createJadwalRambung($guru, $idKelas, 2, '07:45:00', '08:30:00');
        $this->createJadwalRambung($guru, $idKelas, 3, '08:30:00', '09:15:00');

        $this->withSession([
            'id_pengguna' => $guru->id_pengguna,
            'role' => 'guru',
        ]);

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelas)
            ->set('materi', 'Persamaan linear')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('jamTerpilih', [])
            ->assertSet('jadwalAktif', null)
            ->assertSet('semuaJamTerisi', true)
            ->set('materi', 'Persamaan linear')
            ->call('save')
            ->assertHasErrors('jadwal');

        $this->assertSame(
            [1, 2, 3],
            Jurnal::query()->orderBy('jam_ke')->pluck('jam_ke')->all()
        );
    }

    public function test_jam_otomatis_dihitung_ulang_saat_guru_memilih_kelas_lain(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 07:10:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001');
        $idKelasPertama = $this->createKelasSiswa('XI RPL 1');
        $idKelasKedua = $this->createKelasSiswa('XI RPL 2');

        $this->createJadwalRambung($guru, $idKelasPertama, 1, '07:00:00', '07:45:00');
        $this->createJadwalRambung($guru, $idKelasPertama, 2, '07:45:00', '08:30:00');
        $this->createJadwalRambung($guru, $idKelasKedua, 1, '09:00:00', '09:40:00');
        $this->createJadwalRambung($guru, $idKelasKedua, 2, '09:40:00', '10:20:00');

        $this->withSession([
            'id_pengguna' => $guru->id_pengguna,
            'role' => 'guru',
        ]);

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelasPertama)
            ->assertSet('jam_ke', 1)
            ->assertSet('jamMulaiPembelajaran', '07:00:00')
            ->assertSet('jamTerpilih', [1, 2])
            ->set('id_kelas', $idKelasKedua)
            ->assertSet('id_kelas', $idKelasKedua)
            ->assertSet('jam_ke', 1)
            ->assertSet('jamMulaiPembelajaran', '09:00:00')
            ->assertSet('jamTerpilih', [1, 2]);
    }

    private function createPengguna(string $nip): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nip,
            'password' => 'password',
            'role' => 'guru',
        ]);
    }

    private function createKelasSiswa(string $namaKelas = 'XI RPL 1'): int
    {
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => $namaKelas,
            'jumlah_siswa' => 2,
        ], 'id_kelas');

        Siswa::create([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Ahmad',
        ]);

        Siswa::create([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Budi',
        ]);

        return $idKelas;
    }

    private function createJadwalRambung(
        Pengguna $guru,
        int $idKelas,
        int $jamKe,
        string $jamMulai,
        string $jamSelesai
    ): Jadwal {
        return Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => 'Senin',
            'jam_ke' => $jamKe,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
        ]);
    }
}
