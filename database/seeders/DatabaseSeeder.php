<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $kelasRpl2Id = $this->upsertKelas('XI RPL 2', 'Dewi Anjani, S.Pd');
        $kelasRpl1Id = $this->upsertKelas('XI RPL 1');

        $this->upsertPengguna('ADMIN001', 'Administrator SIJAGA', 'admin');
        $this->upsertPengguna('WAKASEK001', 'Wakil Kepala Sekolah 1', 'wakasek', noHp: '083835133274');
        $this->upsertPengguna('WAKASEK002', 'Wakil Kepala Sekolah 2', 'wakasek', noHp: '087889677251');

        $guru = [
            ['GURU001', 'Anisa Kusumawati, S.Pd', 'Kreativitas, Inovasi, dan Kewirausahaan'],
            ['GURU002', 'Fajar Wahyu Pratiwi, S.S', 'Bahasa Inggris'],
            ['GURU003', 'Badrus Sulaiman, S.Pd', 'Konsentrasi RPL'],
            ['GURU004', 'Lutfia Marsalina, S.Pd.I, M.Pd', 'Matematika'],
            ['GURU005', 'Zainul Arifin, S.Pd', 'PJOK'],
            ['GURU006', 'Kurnila Putri Islamawati, S.Pd', 'Konsentrasi RPL'],
            ['GURU007', 'Hendro Suwignyo, ST', 'Mapel Pilihan RPL'],
            ['GURU008', 'Wiwik Yuniarsih, S.Pd', 'Pendidikan Pancasila'],
            ['GURU009', 'Erna Qoriah, S.E.', 'Sejarah'],
            ['GURU010', 'Sulistyowati, SS', 'Bahasa Jepang'],
            ['GURU011', 'Widodo, S.Pd', 'BK'],
            ['GURU012', 'Laili Ernawati, S.Pd', 'Bahasa Jawa'],
            ['GURU013', 'Winartin, S.Pd', 'Bahasa Indonesia'],
            ['GURU014', 'Mufatiroh, S.Ag', 'Pendidikan Agama Islam dan Budi Pekerti'],
            ['GURU015', 'Shinta Indyar Shanty Susanto, S.Kom', 'Konsentrasi RPL'],
            ['GURU016', 'Isti Mufadah, S.Pd', 'Bahasa Inggris'],
            ['GURU017', 'Rizki Putri Wulandari, S.Pd', 'Bahasa Jawa'],
            ['GURU018', 'Umi Kulsum, S.Pd', 'Bahasa Indonesia'],
        ];

        foreach ($guru as [$nip, $nama, $mapel]) {
            $this->upsertPengguna($nip, $nama, 'guru', $mapel);
        }

        $this->upsertPengguna('SEKRE001', 'Sekretaris Kelas XI RPL 2', 'sekretaris', idKelas: $kelasRpl2Id);
        $this->upsertPengguna('SEKRE002', 'Sekretaris Kelas XI RPL 1', 'sekretaris', idKelas: $kelasRpl1Id);

        $siswa = [
            'MARVEL MAULANA SAPUTRA',
            'MARWA RIZQIANI PUTRI',
            'MAULANA QUBRO ALGHOZALI',
            'MOCHAMAD RAFI NUR ALFAN',
            'MOCHAMMAD WILDAN SEPTIANO PRASETYO',
            'MOHAMMAD REISYA APRILLIAWAN',
            'MUHAMMAD BAGUS PRASETIYO',
            'MUHAMMAD ADIP SOFIYULLOH',
            'MUHAMMAD ALBYAN AULIA',
            'MUHAMMAD DUDE FAHREZI',
            'MUHAMMAD FUAD HASAN',
            'MUHAMMAD ILHAM NASHRULLAH',
            'MUHAMMAD RAFA AZRYELLO FARISHUTAMA',
            'MUHAMMAD RAFFI ARKHAN',
            'MUHAMMAD SAIFUDDIN',
            'NANDA AURELIA KHOIRUNNISAA',
            'NASWA PUTRI BINTANG FEBRIANA',
            'NAZWA AFIFAH ANWAR',
            'NITA DWI LARASATI',
            'PRATAMA REZKIANSYAH WIDIANTO',
            'PUTRI LIANASARI',
            'PUTRI ZAHWA RUSDIANA',
            'RAGA SYAHPUTRA ARIFIN',
            'RANIA NURILLAH',
            'RIRIN SRI WAHYUNI',
            'SEREN KHANZAA AZYLA',
            'SEVIA DWI NOVITASARI',
            "SHALSABILLA PUTRI NUR'AINI",
            'SKANDINAVIA',
            'SYAFIQI ERDANSYAH RAMADAN',
            'VANESSA FLORIS',
            'VANISSA DEWI PUTRI RIANTO',
            'VARADITA APRILIANDINI',
            'WILDAN RAMADAN',
            'ZEFITRA ANANDA WIJAYA',
            'SALMA FIKRIATUL AZIZAH',
        ];

        foreach ([$kelasRpl1Id, $kelasRpl2Id] as $kelasId) {
            foreach ($siswa as $namaSiswa) {
                DB::table('siswa')->updateOrInsert(
                    ['id_kelas' => $kelasId, 'nama_siswa' => $namaSiswa],
                    []
                );
            }
        }

        $guruIds = DB::table('pengguna')
            ->whereIn('nip', array_column($guru, 0))
            ->pluck('id_pengguna', 'nip');

        $this->seedJadwal($kelasRpl2Id, [
            ['GURU001', 'Senin', 2, '07:40:00', '08:20:00'],
            ['GURU001', 'Senin', 3, '08:20:00', '09:00:00'],
            ['GURU001', 'Senin', 4, '09:00:00', '09:40:00'],
            ['GURU002', 'Senin', 5, '10:00:00', '10:35:00'],
            ['GURU002', 'Senin', 6, '10:35:00', '11:10:00'],
            ['GURU003', 'Senin', 7, '11:10:00', '11:45:00'],
            ['GURU003', 'Senin', 8, '13:15:00', '13:50:00'],
            ['GURU003', 'Senin', 9, '13:50:00', '14:25:00'],
            ['GURU003', 'Senin', 10, '14:25:00', '15:00:00'],
            ['GURU004', 'Selasa', 1, '07:00:00', '07:40:00'],
            ['GURU004', 'Selasa', 2, '07:40:00', '08:20:00'],
            ['GURU004', 'Selasa', 3, '08:20:00', '09:00:00'],
            ['GURU002', 'Selasa', 4, '09:00:00', '09:40:00'],
            ['GURU002', 'Selasa', 5, '10:00:00', '10:35:00'],
            ['GURU005', 'Selasa', 6, '10:35:00', '11:10:00'],
            ['GURU005', 'Selasa', 7, '11:10:00', '11:45:00'],
            ['GURU003', 'Selasa', 8, '13:15:00', '13:50:00'],
            ['GURU003', 'Selasa', 9, '13:50:00', '14:25:00'],
            ['GURU003', 'Selasa', 10, '14:25:00', '15:00:00'],
            ['GURU006', 'Rabu', 1, '07:00:00', '07:40:00'],
            ['GURU006', 'Rabu', 2, '07:40:00', '08:20:00'],
            ['GURU006', 'Rabu', 3, '08:20:00', '09:00:00'],
            ['GURU006', 'Rabu', 4, '09:00:00', '09:40:00'],
            ['GURU007', 'Rabu', 5, '10:00:00', '10:35:00'],
            ['GURU007', 'Rabu', 6, '10:35:00', '11:10:00'],
            ['GURU008', 'Rabu', 7, '11:10:00', '11:45:00'],
            ['GURU008', 'Rabu', 8, '13:15:00', '13:50:00'],
            ['GURU009', 'Rabu', 9, '13:50:00', '14:25:00'],
            ['GURU009', 'Rabu', 10, '14:25:00', '15:00:00'],
            ['GURU010', 'Kamis', 1, '07:00:00', '07:40:00'],
            ['GURU010', 'Kamis', 2, '07:40:00', '08:20:00'],
            ['GURU011', 'Kamis', 3, '08:20:00', '09:00:00'],
            ['GURU011', 'Kamis', 4, '09:00:00', '09:40:00'],
            ['GURU012', 'Kamis', 5, '10:00:00', '10:35:00'],
            ['GURU012', 'Kamis', 6, '10:35:00', '11:10:00'],
            ['GURU006', 'Kamis', 7, '11:10:00', '11:45:00'],
            ['GURU006', 'Kamis', 8, '13:15:00', '13:50:00'],
            ['GURU006', 'Kamis', 9, '13:50:00', '14:25:00'],
            ['GURU006', 'Kamis', 10, '14:25:00', '15:00:00'],
            ['GURU013', 'Jumat', 2, '07:30:00', '08:00:00'],
            ['GURU013', 'Jumat', 3, '08:00:00', '08:30:00'],
            ['GURU013', 'Jumat', 4, '08:30:00', '09:00:00'],
            ['GURU014', 'Jumat', 5, '09:00:00', '09:30:00'],
            ['GURU014', 'Jumat', 6, '09:50:00', '10:20:00'],
            ['GURU014', 'Jumat', 7, '10:20:00', '10:50:00'],
            ['GURU003', 'Jumat', 8, '10:50:00', '11:20:00'],
            ['GURU003', 'Jumat', 9, '13:00:00', '13:30:00'],
            ['GURU003', 'Jumat', 10, '13:30:00', '14:00:00'],
            ['GURU001', 'Jumat', 11, '14:00:00', '14:30:00'],
            ['GURU001', 'Jumat', 12, '14:30:00', '15:00:00'],
        ], $guruIds);

        $this->seedJadwal($kelasRpl1Id, [
            ['GURU014', 'Senin', 1, '07:00:00', '07:40:00'],
            ['GURU011', 'Senin', 5, '10:00:00', '10:35:00'],
            ['GURU011', 'Senin', 6, '10:35:00', '11:10:00'],
            ['GURU006', 'Senin', 8, '13:15:00', '13:50:00'],
            ['GURU006', 'Senin', 9, '13:50:00', '14:25:00'],
            ['GURU006', 'Senin', 10, '14:25:00', '15:00:00'],
            ['GURU006', 'Selasa', 1, '07:00:00', '07:40:00'],
            ['GURU006', 'Selasa', 2, '07:40:00', '08:20:00'],
            ['GURU006', 'Selasa', 3, '08:20:00', '09:00:00'],
            ['GURU015', 'Selasa', 5, '10:00:00', '10:35:00'],
            ['GURU015', 'Selasa', 6, '10:35:00', '11:10:00'],
            ['GURU001', 'Selasa', 9, '13:50:00', '14:25:00'],
            ['GURU001', 'Selasa', 10, '14:25:00', '15:00:00'],
            ['GURU015', 'Rabu', 1, '07:00:00', '07:40:00'],
            ['GURU015', 'Rabu', 2, '07:40:00', '08:20:00'],
            ['GURU015', 'Rabu', 3, '08:20:00', '09:00:00'],
            ['GURU016', 'Rabu', 5, '10:00:00', '10:35:00'],
            ['GURU016', 'Rabu', 6, '10:35:00', '11:10:00'],
            ['GURU009', 'Rabu', 7, '11:10:00', '11:45:00'],
            ['GURU012', 'Rabu', 9, '13:50:00', '14:25:00'],
            ['GURU012', 'Rabu', 10, '14:25:00', '15:00:00'],
            ['GURU015', 'Kamis', 1, '07:00:00', '07:40:00'],
            ['GURU015', 'Kamis', 2, '07:40:00', '08:20:00'],
            ['GURU007', 'Kamis', 5, '10:00:00', '10:35:00'],
            ['GURU007', 'Kamis', 6, '10:35:00', '11:10:00'],
            ['GURU005', 'Kamis', 7, '11:10:00', '11:45:00'],
            ['GURU018', 'Kamis', 9, '13:50:00', '14:25:00'],
            ['GURU018', 'Kamis', 10, '14:25:00', '15:00:00'],
            ['GURU001', 'Jumat', 2, '07:30:00', '08:00:00'],
            ['GURU001', 'Jumat', 3, '08:00:00', '08:30:00'],
            ['GURU001', 'Jumat', 4, '08:30:00', '09:00:00'],
            ['GURU008', 'Jumat', 5, '09:00:00', '09:30:00'],
            ['GURU010', 'Jumat', 6, '09:50:00', '10:20:00'],
            ['GURU016', 'Jumat', 7, '10:20:00', '10:50:00'],
            ['GURU004', 'Jumat', 8, '10:50:00', '11:20:00'],
            ['GURU004', 'Jumat', 9, '13:00:00', '13:30:00'],
            ['GURU004', 'Jumat', 10, '13:30:00', '14:00:00'],
        ], $guruIds);

        $this->seedJadwalPiket($guruIds);

        $this->call(DummyAbsensiSiswaSeeder::class);
    }

    private function upsertKelas(string $namaKelas, ?string $waliKelas = null): int
    {
        DB::table('kelas')->updateOrInsert(
            ['nama_kelas' => $namaKelas],
            ['wali_kelas' => $waliKelas, 'jumlah_siswa' => 36]
        );

        return (int) DB::table('kelas')->where('nama_kelas', $namaKelas)->value('id_kelas');
    }

    private function upsertPengguna(
        string $nip,
        string $nama,
        string $role,
        ?string $mapel = null,
        ?int $idKelas = null,
        ?string $noHp = null,
    ): void {
        DB::table('pengguna')->updateOrInsert(
            ['nip' => $nip],
            [
                'nama' => $nama,
                'mapel_diampu' => $mapel,
                'no_hp' => $noHp,
                'status_kepegawaian' => in_array($role, ['guru', 'wakasek'], true) ? 'PNS' : null,
                'password' => Hash::make('guru123'),
                'role' => $role,
                'id_kelas' => $idKelas,
            ]
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int, 3: string, 4: string}>  $jadwal
     * @param  Collection<string, int>  $guruIds
     */
    private function seedJadwal(int $idKelas, array $jadwal, $guruIds): void
    {
        foreach ($jadwal as [$nip, $hari, $jamKe, $mulai, $selesai]) {
            DB::table('jadwal')->updateOrInsert(
                ['id_kelas' => $idKelas, 'hari' => $hari, 'jam_ke' => $jamKe],
                [
                    'id_guru' => $guruIds[$nip],
                    'jam_mulai' => $mulai,
                    'jam_selesai' => $selesai,
                ]
            );
        }
    }

    /**
     * @param  Collection<string, int>  $guruIds
     */
    private function seedJadwalPiket($guruIds): void
    {
        foreach ([
            ['GURU001', 'Senin'],
            ['GURU002', 'Selasa'],
        ] as [$nip, $hari]) {
            DB::table('guru_piket')->updateOrInsert(
                ['id_pengguna' => $guruIds[$nip], 'hari' => $hari],
                ['jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00', 'aktif' => true]
            );
        }
    }
}
