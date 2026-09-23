<?php

namespace Database\Seeders;

use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

class JadwalPiketSeeder extends Seeder
{
    public function run(): void
    {
        $jadwalPiket = [
            ['tanggal' => '2026-09-22', 'nama' => 'Yusuf Hidayat, S.Kom', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
            ['tanggal' => '2026-09-23', 'nama' => 'Bambang Wijaya, S.Pd', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
            ['tanggal' => '2026-09-24', 'nama' => 'Hendra Gunawan, S.Kom', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
            ['tanggal' => '2026-09-25', 'nama' => 'Rahmawati, S.Kom', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
            ['tanggal' => '2026-09-26', 'nama' => 'Dewi Anjani, S.Pd', 'mulai' => '07:00:00', 'selesai' => '11:00:00'],
            ['tanggal' => '2026-09-27', 'nama' => 'Agus Setiawan, S.Kom', 'mulai' => '07:00:00', 'selesai' => '12:00:00'],
            ['tanggal' => '2026-10-05', 'nama' => 'Yusuf Hidayat, S.Kom', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
            ['tanggal' => '2026-10-12', 'nama' => 'Rahmawati, S.Kom', 'mulai' => '07:00:00', 'selesai' => '15:00:00'],
        ];

        foreach ($jadwalPiket as $data) {
            $guru = Pengguna::query()->where('nama', $data['nama'])->firstOrFail();

            JadwalPiket::query()->updateOrCreate(
                [
                    'id_guru' => $guru->id_pengguna,
                    'tanggal' => $data['tanggal'],
                    'jam_mulai' => $data['mulai'],
                    'jam_selesai' => $data['selesai'],
                ],
                [
                    'status' => 'Aktif',
                    'keterangan' => 'Guru Piket',
                ]
            );
        }

        $yusuf = Pengguna::query()->where('nama', 'Yusuf Hidayat, S.Kom')->firstOrFail();
        $kelas = Kelas::query()->where('nama_kelas', 'XI RPL 2')->firstOrFail();

        Jadwal::query()->updateOrCreate(
            [
                'id_guru' => $yusuf->id_pengguna,
                'id_kelas' => $kelas->id_kelas,
                'hari' => 'Senin',
                'jam_ke' => 2,
            ],
            [
                'jam_mulai' => '07:40:00',
                'jam_selesai' => '08:20:00',
            ]
        );
    }
}
