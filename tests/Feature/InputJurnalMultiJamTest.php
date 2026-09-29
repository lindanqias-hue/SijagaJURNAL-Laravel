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

    public function test_satu_input_menyimpan_jurnal_untuk_semua_jam_berurutan_yang_dipilih(): void
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
            ->call('toggleJamTerpilih', 2)
            ->call('toggleJamTerpilih', 3)
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

    public function test_penyimpanan_dibatalkan_jika_salah_satu_jam_sudah_tercatat(): void
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
            ->assertHasNoErrors();

        $this->assertSame(
            [1],
            Jurnal::query()->pluck('jam_ke')->all()
        );

        Livewire::test('input-jurnal')
            ->set('id_kelas', $idKelas)
            ->call('toggleJamTerpilih', 2)
            ->assertSet('jamTerpilih', [1, 2])
            ->set('materi', 'Persamaan linear')
            ->call('save')
            ->assertHasErrors('jamTerpilih');

        $this->assertSame(
            [1],
            Jurnal::query()->orderBy('jam_ke')->pluck('jam_ke')->all()
        );
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

    private function createKelasSiswa(): int
    {
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 1',
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
