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

        $kelas = Kelas::query()->where('nama_kelas', 'XI RPL 2')->firstOrFail();

        $waktuPerJam = [
            1 => ['07:00:00', '07:40:00'],
            2 => ['07:40:00', '08:20:00'],
            3 => ['08:20:00', '09:00:00'],
            4 => ['09:00:00', '09:40:00'],
            5 => ['10:00:00', '10:35:00'],
            6 => ['10:35:00', '11:10:00'],
            7 => ['11:10:00', '11:45:00'],
            8 => ['13:15:00', '13:50:00'],
            9 => ['13:50:00', '14:25:00'],
            10 => ['14:25:00', '15:00:00'],
        ];

        $guruDummy = Pengguna::query()
            ->where('nip', 'like', 'DUMMY-GURU-%')
            ->orderBy('nip')
            ->get();

        foreach ($guruDummy as $index => $guru) {
            $jamKe = $index + 1;
            [$jamMulai, $jamSelesai] = $waktuPerJam[$jamKe];

            foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $hari) {
                Jadwal::query()->updateOrCreate(
                    [
                        'id_guru' => $guru->id_pengguna,
                        'id_kelas' => $kelas->id_kelas,
                        'hari' => $hari,
                        'jam_ke' => $jamKe,
                    ],
                    [
                        'jam_mulai' => $jamMulai,
                        'jam_selesai' => $jamSelesai,
                    ]
                );
            }
        }
    }
}
