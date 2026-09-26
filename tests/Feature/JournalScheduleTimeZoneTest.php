<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pengguna;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class JournalScheduleTimeZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_uses_the_jakarta_weekday_and_rejects_a_tampered_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 17:30:00', 'UTC'));
        $guru = Pengguna::create([
            'nip' => 'GURU001',
            'nama' => 'Guru Uji',
            'password' => 'password',
            'role' => 'guru',
        ]);
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'XI RPL Sabtu',
            'jumlah_siswa' => 1,
        ], 'id_kelas');
        DB::table('siswa')->insert([
            'id_kelas' => $idKelas,
            'nama_siswa' => 'Siswa Uji',
        ]);
        DB::table('jadwal')->insert([
            'id_guru' => $guru->id_pengguna,
            'id_kelas' => $idKelas,
            'hari' => 'Sabtu',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
        ]);

        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'role' => 'guru']);

        Livewire::test('input-jurnal')
            ->assertSet('tanggal', '2026-09-26')
            ->assertSet('id_kelas', $idKelas)
            ->assertSee('XI RPL Sabtu')
            ->set('tanggal', '2026-09-25')
            ->assertSet('tanggal', '2026-09-26');
    }

    public function test_journal_form_loads_real_rpl_demo_schedule_and_students_from_the_seeder(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 08:00:00', 'Asia/Jakarta'));
        $this->seed(DatabaseSeeder::class);

        $guru = Pengguna::query()->where('nip', 'GURU003')->firstOrFail();
        $kelas = Kelas::query()->where('nama_kelas', 'XI RPL 2')->firstOrFail();
        $this->withSession(['id_pengguna' => $guru->id_pengguna, 'role' => 'guru']);

        $component = Livewire::test('input-jurnal');

        $this->assertSame('Konsentrasi RPL', $component->get('mapelAktif'));
        $this->assertSame($kelas->id_kelas, $component->get('id_kelas'));
        $this->assertSame('2026-09-28', $component->get('tanggal'));
        $this->assertCount(36, collect($component->get('siswa')));

        $component
            ->assertSee('XI RPL 2')
            ->assertSee('Konsentrasi RPL')
            ->assertSee('11:10')
            ->call('bukaAbsensiSiswa')
            ->assertSee('MARVEL MAULANA SAPUTRA');
    }
}
