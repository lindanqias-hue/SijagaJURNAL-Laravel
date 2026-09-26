<?php

namespace Tests\Feature;

use App\Models\Dispensasi;
use App\Models\GuruPiket;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GuruPiketSuratSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_piket_creates_individual_requests_and_notifies_the_selected_wakasek(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));
        Storage::fake('local');
        config([
            'services.whatsapp.messages_url' => 'https://graph.example.test/messages',
            'services.whatsapp.access_token' => 'test-access-token',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'graph.example.test/messages' => Http::response(['messages' => [['id' => 'wamid.test']]]),
        ]);
        $fixture = $this->createFixture(true);
        $this->withSession([
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'nama' => $fixture['guru']->nama,
            'role' => 'guru',
        ]);

        $component = Livewire::test('dispensasi')
            ->set('id_kelas', $fixture['idKelas'])
            ->set('siswaTerpilih', $fixture['idSiswa'])
            ->set('jenis_dispensasi', 'Sehari Penuh')
            ->set('idWakasek', (string) $fixture['wakasek']->id_pengguna)
            ->set('alasan', 'Kegiatan sekolah di luar')
            ->set('lampiran', UploadedFile::fake()->image('bukti.png', 10, 10));

        $this->assertSame('image/png', $component->get('lampiran')->getMimeType());
        $component->call('simpan')->assertHasNoErrors();

        $dispensasi = Dispensasi::query()->orderBy('id_dispensasi')->get();
        $this->assertCount(2, $dispensasi);
        $this->assertSame(2, $dispensasi->pluck('token')->unique()->count());
        $this->assertSame(2, $dispensasi->pluck('ticket_token')->unique()->count());
        $this->assertTrue($dispensasi->every(fn (Dispensasi $item): bool => $item->token !== $item->ticket_token));
        $this->assertSame(2, $dispensasi->where('status', Dispensasi::STATUS_MENUNGGU)->count());
        $this->assertTrue($dispensasi->every(
            fn (Dispensasi $item): bool => $item->id_wakasek === $fixture['wakasek']->id_pengguna
                && $item->jenis_surat === Dispensasi::JENIS_SURAT_DISPENSASI
                && Storage::disk('local')->exists($item->lampiran_path)
        ));
        foreach ($dispensasi as $item) {
            $approvalUrl = route('approve-dispensasi', [
                'token' => $item->token,
                'wakasek' => $fixture['wakasek']->id_pengguna,
            ]);
            Http::assertSent(
                fn (Request $request): bool => $request['to'] === '6283835133274'
                    && str_contains($request['text']['body'], $approvalUrl)
            );
        }
    }

    public function test_external_leave_is_immediately_approved_without_wakasek_review(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));
        Storage::fake('local');
        $fixture = $this->createFixture(true);
        $this->withSession([
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'nama' => $fixture['guru']->nama,
            'role' => 'guru',
        ]);

        Livewire::test('dispensasi')
            ->set('id_kelas', $fixture['idKelas'])
            ->set('siswaTerpilih', [$fixture['idSiswa'][0]])
            ->set('jenisSurat', 'Izin')
            ->set('alasan', 'Surat izin orang tua')
            ->set('lampiran', UploadedFile::fake()->image('izin.png', 10, 10))
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dispensasi', [
            'id_siswa' => $fixture['idSiswa'][0],
            'jenis_surat' => Dispensasi::JENIS_SURAT_IZIN,
            'status' => Dispensasi::STATUS_DISETUJUI,
            'id_wakasek' => null,
        ]);
    }

    public function test_per_hour_schedule_choices_are_available_outside_an_active_lesson(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(13, 0));
        Storage::fake('local');
        $fixture = $this->createFixture(true);

        foreach (
            [
                [1, '07:00:00', '07:40:00'],
                [2, '07:40:00', '08:20:00'],
            ] as [$jamKe, $jamMulai, $jamSelesai]
        ) {
            DB::table('jadwal')->insert([
                'id_guru' => $fixture['guru']->id_pengguna,
                'id_kelas' => $fixture['idKelas'],
                'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
                'jam_ke' => $jamKe,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
            ]);
        }

        $this->withSession([
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'nama' => $fixture['guru']->nama,
            'role' => 'guru',
        ]);

        Livewire::test('dispensasi')
            ->set('id_kelas', $fixture['idKelas'])
            ->set('jenis_dispensasi', 'Per Jam')
            ->assertSet('jam_ke_mulai', 1)
            ->assertSet('jam_ke_selesai', 1)
            ->set('jam_ke_mulai', 2)
            ->assertSet('jam_mulai', '07:40:00')
            ->assertSee('07:40')
            ->set('siswaTerpilih', [$fixture['idSiswa'][0]])
            ->set('idWakasek', (string) $fixture['wakasek']->id_pengguna)
            ->set('alasan', 'Keluar sekolah untuk kegiatan')
            ->set('lampiran', UploadedFile::fake()->image('bukti-per-jam.png', 10, 10))
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dispensasi', [
            'id_siswa' => $fixture['idSiswa'][0],
            'jenis_dispensasi' => 'Per Jam',
            'jam_ke_mulai' => 2,
            'jam_ke_selesai' => 2,
            'status' => Dispensasi::STATUS_MENUNGGU,
        ]);
    }

    public function test_per_mapel_choices_show_the_teacher_and_lesson_time_for_the_selected_class(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(13, 0));
        $fixture = $this->createFixture(true);
        DB::table('pengguna')
            ->where('id_pengguna', $fixture['guru']->id_pengguna)
            ->update(['mapel_diampu' => 'Matematika']);
        DB::table('jadwal')->insert([
            'id_guru' => $fixture['guru']->id_pengguna,
            'id_kelas' => $fixture['idKelas'],
            'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
        ]);
        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'mapel_diampu' => 'Matematika',
        ]);
        $this->withSession([
            'id_pengguna' => $fixture['guru']->id_pengguna,
            'nama' => $fixture['guru']->nama,
            'role' => 'guru',
        ]);

        $component = Livewire::test('dispensasi')
            ->set('id_kelas', $fixture['idKelas']);

        $jadwalPertama = collect($component->get('jadwalHariIni'))->first();
        $this->assertSame('Guru Piket', data_get($jadwalPertama, 'nama_guru'));
        $this->assertSame('Matematika', data_get($jadwalPertama, 'mapel_diampu'));

        $component
            ->set('jenis_dispensasi', 'Per Mapel')
            ->assertSet('mapel', 'Matematika')
            ->assertSee('Matematika')
            ->assertSee('Guru Piket')
            ->assertSee('07:00')
            ->assertSeeHtml('type="file"');
    }

    public function test_off_duty_teacher_cannot_submit_even_if_the_livewire_action_is_called_directly(): void
    {
        $this->travelTo(Carbon::now('Asia/Jakarta')->setTime(8, 0));
        $guru = $this->createPengguna('GURU009', 'Guru Tidak Piket');
        $this->withSession([
            'id_pengguna' => $guru->id_pengguna,
            'nama' => $guru->nama,
            'role' => 'guru',
        ]);

        Livewire::test('dashboard')
            ->assertSee(route('guru-piket'))
            ->assertSee(route('dispensasi'));
        Livewire::test('guru-piket')->assertSee('Anda tidak memiliki hak akses piket hari ini');
        Livewire::test('dispensasi')
            ->assertSee('Anda tidak memiliki hak akses piket hari ini')
            ->assertSeeHtml('disabled');

        Livewire::test('dispensasi')
            ->call('simpan')
            ->assertHasErrors(['piket']);

        $this->assertDatabaseCount('dispensasi', 0);
    }

    /** @return array{guru: Pengguna, wakasek: Pengguna, idKelas: int, idSiswa: array<int, int>} */
    private function createFixture(bool $assignPiket): array
    {
        $guru = $this->createPengguna('GURU001', 'Guru Piket');
        $wakasek = Pengguna::create([
            'nip' => 'WAKASEK001',
            'nama' => 'Wakasek Uji',
            'no_hp' => '083835133274',
            'password' => 'password',
            'role' => 'wakasek',
        ]);
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL 2',
            'jumlah_siswa' => 2,
        ], 'id_kelas');
        $idSiswa = [];

        foreach (['Siswa Satu', 'Siswa Dua'] as $namaSiswa) {
            $idSiswa[] = DB::table('siswa')->insertGetId([
                'id_kelas' => $idKelas,
                'nama_siswa' => $namaSiswa,
            ], 'id_siswa');
        }

        if ($assignPiket) {
            GuruPiket::create([
                'id_pengguna' => $guru->id_pengguna,
                'hari' => Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'),
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '15:00:00',
                'aktif' => true,
            ]);
        }

        return compact('guru', 'wakasek', 'idKelas', 'idSiswa');
    }

    private function createPengguna(string $nip, string $nama): Pengguna
    {
        return Pengguna::create([
            'nip' => $nip,
            'nama' => $nama,
            'password' => 'password',
            'role' => 'guru',
        ]);
    }
}
