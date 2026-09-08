<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | KELAS
        |--------------------------------------------------------------------------
        */

        DB::table('kelas')->updateOrInsert(
            ['id_kelas' => 4],
            [
                'nama_kelas' => 'XI RPL 2',
                'wali_kelas' => 'Dewi Anjani, S.Pd',
                'jumlah_siswa' => 36,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | GURU
        |--------------------------------------------------------------------------
        */

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
        ];

        foreach ($guru as [$nip, $nama, $mapel]) {
            DB::table('pengguna')->updateOrInsert(
                ['nip' => $nip],
                [
                    'nama' => $nama,
                    'mapel_diampu' => $mapel,
                    'no_hp' => null,
                    'status_kepegawaian' => 'PNS',
                    'password' => 'guru123',
                    'role' => 'guru',
                    'id_kelas' => null,
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GURU PIKET
        |--------------------------------------------------------------------------
        */

        $guruPiket = [
            ['PIKET001', 'Budi Santoso, S.Pd'],
            ['PIKET002', 'Siti Rahmawati, S.Pd'],
        ];

        foreach ($guruPiket as [$nip, $nama]) {
            DB::table('pengguna')->updateOrInsert(
                ['nip' => $nip],
                [
                    'nama' => $nama,
                    'mapel_diampu' => null,
                    'no_hp' => null,
                    'status_kepegawaian' => 'PNS',
                    'password' => 'guru123',
                    'role' => 'guru_piket',
                    'id_kelas' => null,
                ]
            );
        }

                /*
        |--------------------------------------------------------------------------
        | SEKRETARIS XI RPL 2
        |--------------------------------------------------------------------------
        */

        DB::table('pengguna')->updateOrInsert(
            ['nip' => 'SEKRETARIS001'],
            [
                'nama' => 'Nama Sekretaris',
                'mapel_diampu' => null,
                'no_hp' => null,
                'status_kepegawaian' => null,
                'password' => 'sekretaris123',
                'role' => 'sekretaris',
                'id_kelas' => 4,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | SISWA XI RPL 2
        |--------------------------------------------------------------------------
        */

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

        foreach ($siswa as $nama) {
            DB::table('siswa')->updateOrInsert(
                [
                    'id_kelas' => 4,
                    'nama_siswa' => $nama,
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | JADWAL
        |--------------------------------------------------------------------------
        */

        $idAnisa = DB::table('pengguna')->where('nip', 'GURU001')->value('id_pengguna');
        $idFajar = DB::table('pengguna')->where('nip', 'GURU002')->value('id_pengguna');
        $idBadrus = DB::table('pengguna')->where('nip', 'GURU003')->value('id_pengguna');
        $idLutfia = DB::table('pengguna')->where('nip', 'GURU004')->value('id_pengguna');
        $idZainul = DB::table('pengguna')->where('nip', 'GURU005')->value('id_pengguna');
        $idKurnila = DB::table('pengguna')->where('nip', 'GURU006')->value('id_pengguna');
        $idHendro = DB::table('pengguna')->where('nip', 'GURU007')->value('id_pengguna');
        $idWiwik = DB::table('pengguna')->where('nip', 'GURU008')->value('id_pengguna');
        $idErna = DB::table('pengguna')->where('nip', 'GURU009')->value('id_pengguna');
        $idSulistyowati = DB::table('pengguna')->where('nip', 'GURU010')->value('id_pengguna');
        $idWidodo = DB::table('pengguna')->where('nip', 'GURU011')->value('id_pengguna');
        $idLaili = DB::table('pengguna')->where('nip', 'GURU012')->value('id_pengguna');
        $idWinartin = DB::table('pengguna')->where('nip', 'GURU013')->value('id_pengguna');
        $idMufatiroh = DB::table('pengguna')->where('nip', 'GURU014')->value('id_pengguna');


        /*
        |--------------------------------------------------------------------------
        | SENIN
        |--------------------------------------------------------------------------
        */

        $this->jadwal($idAnisa, 4, 'Senin', 2, '07:40:00', '08:20:00');
        $this->jadwal($idAnisa, 4, 'Senin', 3, '08:20:00', '09:00:00');
        $this->jadwal($idAnisa, 4, 'Senin', 4, '09:00:00', '09:40:00');

        $this->jadwal($idFajar, 4, 'Senin', 5, '10:00:00', '10:35:00');
        $this->jadwal($idFajar, 4, 'Senin', 6, '10:35:00', '11:10:00');

        $this->jadwal($idBadrus, 4, 'Senin', 7, '11:10:00', '11:45:00');
        $this->jadwal($idBadrus, 4, 'Senin', 8, '13:15:00', '13:50:00');
        $this->jadwal($idBadrus, 4, 'Senin', 9, '13:50:00', '14:25:00');
        $this->jadwal($idBadrus, 4, 'Senin', 10, '14:25:00', '15:00:00');


        /*
        |--------------------------------------------------------------------------
        | SELASA
        |--------------------------------------------------------------------------
        */

        $this->jadwal($idLutfia, 4, 'Selasa', 1, '07:00:00', '07:40:00');
        $this->jadwal($idLutfia, 4, 'Selasa', 2, '07:40:00', '08:20:00');
        $this->jadwal($idLutfia, 4, 'Selasa', 3, '08:20:00', '09:00:00');

        $this->jadwal($idFajar, 4, 'Selasa', 4, '09:00:00', '09:40:00');
        $this->jadwal($idFajar, 4, 'Selasa', 5, '10:00:00', '10:35:00');

        $this->jadwal($idZainul, 4, 'Selasa', 6, '10:35:00', '11:10:00');
        $this->jadwal($idZainul, 4, 'Selasa', 7, '11:10:00', '11:45:00');

        $this->jadwal($idBadrus, 4, 'Selasa', 8, '13:15:00', '13:50:00');
        $this->jadwal($idBadrus, 4, 'Selasa', 9, '13:50:00', '14:25:00');
        $this->jadwal($idBadrus, 4, 'Selasa', 10, '14:25:00', '15:00:00');


        /*
        |--------------------------------------------------------------------------
        | RABU
        |--------------------------------------------------------------------------
        */

        $this->jadwal($idKurnila, 4, 'Rabu', 1, '07:00:00', '07:40:00');
        $this->jadwal($idKurnila, 4, 'Rabu', 2, '07:40:00', '08:20:00');
        $this->jadwal($idKurnila, 4, 'Rabu', 3, '08:20:00', '09:00:00');
        $this->jadwal($idKurnila, 4, 'Rabu', 4, '09:00:00', '09:40:00');

        $this->jadwal($idHendro, 4, 'Rabu', 5, '10:00:00', '10:35:00');
        $this->jadwal($idHendro, 4, 'Rabu', 6, '10:35:00', '11:10:00');

        $this->jadwal($idWiwik, 4, 'Rabu', 7, '11:10:00', '11:45:00');
        $this->jadwal($idWiwik, 4, 'Rabu', 8, '11:45:00', '12:20:00');

        $this->jadwal($idErna, 4, 'Rabu', 9, '13:15:00', '13:50:00');
        $this->jadwal($idErna, 4, 'Rabu', 10, '13:50:00', '14:25:00');


        /*
        |--------------------------------------------------------------------------
        | KAMIS
        |--------------------------------------------------------------------------
        */

        $this->jadwal($idSulistyowati, 4, 'Kamis', 1, '07:00:00', '07:40:00');
        $this->jadwal($idSulistyowati, 4, 'Kamis', 2, '07:40:00', '08:20:00');

        $this->jadwal($idWidodo, 4, 'Kamis', 3, '08:20:00', '09:00:00');
        $this->jadwal($idWidodo, 4, 'Kamis', 4, '09:00:00', '09:40:00');

        $this->jadwal($idLaili, 4, 'Kamis', 5, '10:00:00', '10:35:00');
        $this->jadwal($idLaili, 4, 'Kamis', 6, '10:35:00', '11:10:00');

        $this->jadwal($idKurnila, 4, 'Kamis', 7, '11:10:00', '11:45:00');
        $this->jadwal($idKurnila, 4, 'Kamis', 8, '11:45:00', '12:20:00');
        $this->jadwal($idKurnila, 4, 'Kamis', 9, '13:15:00', '13:50:00');
        $this->jadwal($idKurnila, 4, 'Kamis', 10, '13:50:00', '14:25:00');


        /*
        |--------------------------------------------------------------------------
        | JUMAT
        |--------------------------------------------------------------------------
        */

        $this->jadwal($idWinartin, 4, 'Jumat', 2, '07:30:00', '08:05:00');
        $this->jadwal($idWinartin, 4, 'Jumat', 3, '08:05:00', '08:40:00');
        $this->jadwal($idWinartin, 4, 'Jumat', 4, '08:40:00', '09:15:00');

        $this->jadwal($idMufatiroh, 4, 'Jumat', 5, '09:15:00', '09:50:00');
        $this->jadwal($idMufatiroh, 4, 'Jumat', 6, '09:50:00', '10:25:00');
        $this->jadwal($idMufatiroh, 4, 'Jumat', 7, '10:25:00', '11:00:00');

        $this->jadwal($idBadrus, 4, 'Jumat', 8, '11:00:00', '11:35:00');
        $this->jadwal($idBadrus, 4, 'Jumat', 9, '11:35:00', '12:10:00');
        $this->jadwal($idBadrus, 4, 'Jumat', 10, '12:10:00', '12:45:00');

        $this->jadwal($idAnisa, 4, 'Jumat', 11, '12:45:00', '13:20:00');
        $this->jadwal($idAnisa, 4, 'Jumat', 12, '13:20:00', '13:55:00');


        /*
        |--------------------------------------------------------------------------
        | GURU PIKET
        |--------------------------------------------------------------------------
        */

        $idPiket1 = DB::table('pengguna')
            ->where('nip', 'PIKET001')
            ->value('id_pengguna');

        $idPiket2 = DB::table('pengguna')
            ->where('nip', 'PIKET002')
            ->value('id_pengguna');

        DB::table('guru_piket')->updateOrInsert(
            [
                'id_pengguna' => $idPiket1,
                'hari' => 'Senin',
            ],
            [
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '15:00:00',
                'aktif' => true,
            ]
        );

        DB::table('guru_piket')->updateOrInsert(
            [
                'id_pengguna' => $idPiket2,
                'hari' => 'Selasa',
            ],
            [
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '15:00:00',
                'aktif' => true,
            ]
        );
    }


    private function jadwal(
        $idGuru,
        $idKelas,
        $hari,
        $jamKe,
        $mulai,
        $selesai
    ): void {
        DB::table('jadwal')->updateOrInsert(
            [
                'id_guru' => $idGuru,
                'id_kelas' => $idKelas,
                'hari' => $hari,
                'jam_ke' => $jamKe,
            ],
            [
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
            ]
        );
    }
}