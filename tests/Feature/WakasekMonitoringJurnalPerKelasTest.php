<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class WakasekMonitoringJurnalPerKelasTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_jurnal_mengelompokkan_jurnal_per_kelas(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guruSatu = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $guruDua = $this->createPengguna('GURU002', 'Bela Lestari', 'Bahasa Indonesia');
        $kelasSatu = $this->createKelas('XII IPA 1');
        $kelasDua = $this->createKelas('XI RPL 2');

        $this->createJadwal($guruSatu, $kelasSatu, 1);
        $this->createJadwal($guruSatu, $kelasSatu, 2);
        $this->createJadwal($guruDua, $kelasDua, 1);

        $this->createJurnal($guruSatu, $kelasSatu, 1, 'Divalidasi');
        $this->createJurnal($guruSatu, $kelasSatu, 2, 'Divalidasi');
        $this->createJurnal($guruDua, $kelasDua, 1, 'Menunggu');

        $perKelas = $this->ambilMonitoringPerKelas();

        $this->assertCount(2, $perKelas);
        $this->assertSame(['XII IPA 1', 'XI RPL 2'], $perKelas->pluck('nama_kelas')->all());

        $kelasSatuData = $perKelas->firstWhere('nama_kelas', 'XII IPA 1');
        $this->assertSame(2, $kelasSatuData['total_jam']);
        $this->assertSame(2, $kelasSatuData['terisi']);
        $this->assertSame(0, $kelasSatuData['kosong']);
        $this->assertSame('Lengkap', $kelasSatuData['status_kelas']);

        $kelasDuaData = $perKelas->firstWhere('nama_kelas', 'XI RPL 2');
        $this->assertSame(1, $kelasDuaData['total_jam']);
        $this->assertSame(1, $kelasDuaData['terisi']);
        $this->assertSame(0, $kelasDuaData['kosong']);
        $this->assertSame('Lengkap', $kelasDuaData['status_kelas']);

        Livewire::test('wakasek')
            ->assertSee('XII IPA 1')
            ->assertSee('XI RPL 2')
            ->assertSeeHtml('wire:click="bukaDetailJurnal('.$kelasSatu->id_kelas.')"')
            ->assertDontSee('Andi Saputra')
            ->call('bukaDetailJurnal', $kelasSatu->id_kelas)
            ->assertSee('Andi Saputra')
            ->call('tutupDetailJurnal')
            ->call('bukaDetailJurnal', $kelasDua->id_kelas)
            ->assertSee('Bela Lestari');
    }

    public function test_detail_guru_menandai_guru_yang_belum_mengisi(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guruA = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $guruB = $this->createPengguna('GURU002', 'Bela Lestari', null);
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guruA, $kelas, 1);
        $this->createJadwal($guruA, $kelas, 2);
        $this->createJadwal($guruB, $kelas, 3);

        $this->createJurnal($guruA, $kelas, 1, 'Divalidasi');
        $this->createJurnal($guruA, $kelas, 2, 'Divalidasi');

        $data = $this->ambilMonitoringPerKelas()->first();

        $this->assertSame('Sebagian', $data['status_kelas']);
        $this->assertSame(3, $data['total_jam']);
        $this->assertSame(2, $data['terisi']);
        $this->assertSame(1, $data['kosong']);
        $this->assertCount(2, $data['guru']);

        $guruAData = collect($data['guru'])->firstWhere('nama', 'Andi Saputra');
        $this->assertSame('Matematika', $guruAData['mapel']);
        $this->assertSame([1, 2], $guruAData['jam']);
        $this->assertSame([1, 2], $guruAData['jam_terisi']);
        $this->assertSame([], $guruAData['jam_kosong']);
        $this->assertSame('Terisi Semua', $guruAData['status_jurnal']);
        $this->assertSame('Divalidasi', $guruAData['status_validasi']);

        $guruBData = collect($data['guru'])->firstWhere('nama', 'Bela Lestari');
        $this->assertSame('-', $guruBData['mapel']);
        $this->assertSame([3], $guruBData['jam_kosong']);
        $this->assertSame('Belum Mengisi', $guruBData['status_jurnal']);
        $this->assertNull($guruBData['status_validasi']);

        Livewire::test('wakasek')
            ->assertDontSee('Sudah Mengisi')
            ->call('bukaDetailJurnal', $kelas->id_kelas)
            ->assertSee('Belum Mengisi')
            ->assertSee('Sudah Mengisi');
    }

    public function test_kelas_tanpa_jadwal_hari_ini_tidak_muncul(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelasTerpakai = $this->createKelas('XII IPA 1');
        $kelasKosong = $this->createKelas('XI RPL 2');

        $this->createJadwal($guru, $kelasTerpakai, 1);

        $perKelas = $this->ambilMonitoringPerKelas();

        $this->assertCount(1, $perKelas);
        $this->assertSame([$kelasTerpakai->id_kelas], $perKelas->pluck('id_kelas')->all());
        $this->assertNull($perKelas->firstWhere('id_kelas', $kelasKosong->id_kelas));

        Livewire::test('wakasek')
            ->assertDontSee('XI RPL 2')
            ->assertSee('XII IPA 1');
    }

    public function test_monitoring_jurnal_kosong_bila_tidak_ada_kelas_yang_mengajar(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $this->createKelas('XII IPA 1');

        $this->assertTrue($this->ambilMonitoringPerKelas()->isEmpty());

        Livewire::test('wakasek')
            ->assertSee('Tidak ada kelas yang memiliki jadwal mengajar hari ini.', false);
    }

    public function test_status_validasi_menunggu_saat_jurnal_belum_divalidasi(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guru, $kelas, 1);
        $this->createJadwal($guru, $kelas, 2);

        $this->createJurnal($guru, $kelas, 1, 'Menunggu');
        $this->createJurnal($guru, $kelas, 2, 'Menunggu');

        $guruData = $this->ambilMonitoringPerKelas()->first()['guru'][0];

        $this->assertSame('Terisi Semua', $guruData['status_jurnal']);
        $this->assertSame('Menunggu', $guruData['status_validasi']);
    }

    public function test_status_validasi_ditolak_saat_seluruh_journal_ditolak(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guru, $kelas, 1);
        $this->createJadwal($guru, $kelas, 2);

        $this->createJurnal($guru, $kelas, 1, 'Menunggu');
        $this->createJurnal($guru, $kelas, 2, 'Ditolak');

        $guruData = $this->ambilMonitoringPerKelas()->first()['guru'][0];

        $this->assertSame('Ditolak', $guruData['status_validasi']);
    }

    public function test_kelas_kosong_total_berstatus_kosong(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guru, $kelas, 1);

        $data = $this->ambilMonitoringPerKelas()->first();

        $this->assertSame('Kosong', $data['status_kelas']);
        $this->assertSame(0, $data['terisi']);
        $this->assertSame(1, $data['kosong']);
    }

    public function test_detail_jurnal_dibuka_lewat_modal(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guru, $kelas, 1);
        $this->createJurnal($guru, $kelas, 1, 'Divalidasi');

        $this->ambilMonitoringPerKelas();

        Livewire::test('wakasek')
            ->assertSeeHtml('wire:click="bukaDetailJurnal('.$kelas->id_kelas.')"')
            ->assertDontSee('Tutup Detail', false)
            ->call('bukaDetailJurnal', $kelas->id_kelas)
            ->assertSeeHtml('id="modal-detail-jurnal"')
            ->assertSee('Detail Jurnal — XII IPA 1')
            ->assertSee('Andi Saputra')
            ->assertSee('Tutup Detail')
            ->assertSeeHtml('wire:click="tutupDetailJurnal()"')
            ->call('tutupDetailJurnal')
            ->assertDontSeeHtml('id="modal-detail-jurnal"')
            ->assertDontSee('Tutup Detail');
    }

    public function test_detail_jurnal_ditolak_untuk_kelas_di_luar_monitoring(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');
        $kelasLain = $this->createKelas('XI RPL 2');

        $this->createJadwal($guru, $kelas, 1);

        $this->ambilMonitoringPerKelas();

        Livewire::test('wakasek')
            ->call('bukaDetailJurnal', $kelasLain->id_kelas)
            ->assertNotFound();
    }

    public function test_detail_rekap_dibuka_lewat_modal(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $guru = $this->createPengguna('GURU001', 'Andi Saputra', 'Matematika');
        $kelas = $this->createKelas('XII IPA 1');

        $this->createJadwal($guru, $kelas, 1);

        $this->masukSebagaiWakasek();

        $component = Livewire::test('wakasek');
        $kunci = $component->instance()->getPropertyValue('rekapPerKelas')->first()['kunci'];

        $component
            ->call('bukaRekap', 'rekap-kelas')
            ->assertSeeHtml("wire:click=\"bukaDetailRekap('{$kunci}')\"")
            ->assertDontSee('Tutup Detail', false)
            ->call('bukaDetailRekap', $kunci)
            ->assertSeeHtml('id="modal-detail-rekap"')
            ->assertSee('Rekap XII IPA 1')
            ->assertSee('Tutup Detail')
            ->assertSeeHtml('wire:click="tutupDetailRekap()"')
            ->call('tutupDetailRekap')
            ->assertDontSeeHtml('id="modal-detail-rekap"');
    }

    public function test_markup_tidak_memakai_magic_alpine(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Asia/Jakarta'));

        $this->createKelas('XII IPA 1');

        $this->masukSebagaiWakasek();

        Livewire::test('wakasek')
            ->assertDontSeeHtml('toggleDetail(')
            ->assertDontSeeHtml('openDetails')
            ->assertDontSee('this.$on', false)
            ->assertDontSee('this.$watch', false);
    }

    private function ambilMonitoringPerKelas(): Collection
    {
        $this->masukSebagaiWakasek();

        return Livewire::test('wakasek')->instance()->getPropertyValue('monitoringJurnalPerKelas');
    }

    private function masukSebagaiWakasek(): Pengguna
    {
        $wakasek = Pengguna::create([
            'nip' => 'WAKASEK001',
            'nama' => 'Wali Kelas',
            'password' => 'password',
            'role' => 'wakasek',
        ]);

        $this->withSession([
            'id_pengguna' => $wakasek->id_pengguna,
            'role' => 'wakasek',
        ]);

        return $wakasek;
    }

    private function createPengguna(string $nip, string $nama, ?string $mapel): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nama,
            'password' => 'password',
            'role' => 'guru',
            'mapel_diampu' => $mapel,
        ]);
    }

    private function createKelas(string $namaKelas): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'jumlah_siswa' => 2,
        ]);
    }

    private function createJadwal(Pengguna $guru, Kelas $kelas, int $jamKe): Jadwal
    {
        return Jadwal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $kelas->id_kelas,
            'hari' => 'Senin',
            'jam_ke' => $jamKe,
            'jam_mulai' => sprintf('%02d:00:00', 6 + $jamKe),
            'jam_selesai' => sprintf('%02d:45:00', 6 + $jamKe),
        ]);
    }

    private function createJurnal(Pengguna $guru, Kelas $kelas, int $jamKe, ?string $statusValidasi = 'Menunggu'): Jurnal
    {
        return Jurnal::create([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $kelas->id_kelas,
            'tanggal' => '2026-09-28',
            'jam_ke' => $jamKe,
            'materi' => 'Materi '.$jamKe,
            'jumlah_hadir' => 2,
            'jumlah_tidak_hadir' => 0,
            'status_kehadiran_guru' => 'Hadir',
            'adalah_pengajuan_izin' => false,
            'status_validasi' => $statusValidasi,
        ]);
    }
}
