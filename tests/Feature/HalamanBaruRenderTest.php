<?php

namespace Tests\Feature;

use App\Models\GuruPiket;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanBaruRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_piket_kehadiran_terrender_tanpa_undefined_variable(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));

        $guru = Pengguna::create([
            'nip' => 'GURU100',
            'nama' => 'Guru Piket Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);

        $idKelas = Kelas::insertGetId([
            'nama_kelas' => 'X TKJ 1',
            'jumlah_siswa' => 1,
        ], 'id_kelas');

        Siswa::create([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Piket',
        ]);

        GuruPiket::create([
            'id_pengguna' => $guru->id_pengguna,
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'aktif' => true,
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'nama' => $guru->nama, 'role' => 'guru']);

        Livewire::test('piket-kehadiran')
            ->assertOk()
            ->assertSee('Kehadiran Siswa')
            ->assertSee('Siswa Piket');
    }

    public function test_halaman_guru_piket_terrender_dengan_dua_tab(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));

        $guru = Pengguna::create([
            'nip' => 'GURU101',
            'nama' => 'Guru Piket Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);

        $idKelas = Kelas::insertGetId([
            'nama_kelas' => 'XI RPL 1',
            'jumlah_siswa' => 0,
        ], 'id_kelas');

        Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
        ]);

        GuruPiket::create([
            'id_pengguna' => $guru->id_pengguna,
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'aktif' => true,
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'nama' => $guru->nama, 'role' => 'guru']);

        Livewire::test('guru-piket')
            ->assertOk()
            ->assertSee('Piket & Pemantauan Tugas')
            ->assertSee('Per Kelas')
            ->assertSee('Per Guru');
    }

    public function test_halaman_izin_guru_terrender_dengan_shortcut_waktu(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));

        $guru = Pengguna::create([
            'nip' => 'GURU102',
            'nama' => 'Guru Izin Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);

        $idKelas = Kelas::insertGetId([
            'nama_kelas' => 'XI RPL 3',
            'jumlah_siswa' => 0,
        ], 'id_kelas');

        Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'nama' => $guru->nama, 'role' => 'guru']);

        Livewire::test('izin-guru')
            ->assertOk()
            ->assertSee('Pengajuan Izin Guru')
            ->assertSet('id_kelas', (string) $idKelas)
            ->assertSee('Setengah Hari')
            ->assertSee('Sehari Penuh');
    }

    public function test_pembatalan_semua_kelas_dispensasi_tidak_melempar_type_error(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));

        $guru = Pengguna::create([
            'nip' => 'GURU103',
            'nama' => 'Guru Piket Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);

        $idKelas = Kelas::insertGetId([
            'nama_kelas' => 'X TKJ 2',
            'jumlah_siswa' => 0,
        ], 'id_kelas');

        Siswa::create([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Dispensasi',
        ]);

        GuruPiket::create([
            'id_pengguna' => $guru->id_pengguna,
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '15:00:00',
            'aktif' => true,
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'nama' => $guru->nama, 'role' => 'guru']);

        Livewire::test('dispensasi')
            ->assertOk()
            ->set('id_kelas_terpilih', [(string) $idKelas])
            ->assertSet('siswaList.'.(int) $idKelas.'.0.nama_siswa', 'Siswa Dispensasi')
            // Membatalkan semua pilihan membuat <select multiple> mengirim
            // string kosong. Livewire meng-unset property lalu memanggil
            // updated hook dengan string tersebut.
            ->set('id_kelas_terpilih', '')
            ->assertSet('id_kelas_terpilih', [])
            ->assertSet('siswaList', []);
    }
}
