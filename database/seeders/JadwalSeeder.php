<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JadwalSeeder extends Seeder
{
    private function mataPelajaranJson(): array
    {
        $path = database_path('data/mata_pelajaran.json');

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    public function getMataPelajaran(string $kelas, string $jurusan): array
    {
        $json = $this->mataPelajaranJson();

        $kelasRaw = trim($kelas);
        $jurusanRaw = trim($jurusan);

        $kelasKey = strtoupper(str_replace([' ', '-', '_'], '', $kelasRaw));
        $jurusanKey = strtoupper(str_replace([' ', '-', '_'], '', $jurusanRaw));

        $candidates = [];

        if ($kelasRaw !== '' && $jurusanRaw !== '') {
            $candidates[] = strtoupper($kelasRaw) . ' ' . strtoupper($jurusanRaw);
            $candidates[] = strtoupper($kelasRaw) . ' ' . strtoupper($jurusanRaw) . ' 1';
            $candidates[] = strtoupper($kelasRaw) . ' ' . strtoupper($jurusanRaw) . ' 2';
            $candidates[] = strtoupper($kelasRaw) . ' ' . $jurusanRaw;
            $candidates[] = $kelasRaw . ' ' . $jurusanRaw;
            $candidates[] = trim($kelasRaw . ' ' . $jurusanRaw);
        }

        if (isset($json[$kelasRaw][$jurusanRaw])) {
            return $json[$kelasRaw][$jurusanRaw];
        }

        if (isset($json[$kelasKey][$jurusanKey])) {
            return $json[$kelasKey][$jurusanKey];
        }

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (isset($json[$candidate])) {
                return $json[$candidate];
            }
        }

        $legacyKelasMap = [
            '10' => 'X',
            '11' => 'XI',
            'X' => 'X',
            'XI' => 'XI',
        ];

        $normalizedKelas = $legacyKelasMap[$kelasKey] ?? $kelasKey;

        if (isset($json[$normalizedKelas][$jurusanKey])) {
            return $json[$normalizedKelas][$jurusanKey];
        }

        $jurusanFallback = $jurusanRaw;
        foreach ($json as $kelasName => $kelasMap) {
            if (! is_array($kelasMap)) {
                continue;
            }

            foreach ($kelasMap as $jurusanName => $subjects) {
                $normalizedJurusan = strtoupper(str_replace([' ', '-', '_'], '', $jurusanName));
                $targetJurusan = strtoupper(str_replace([' ', '-', '_'], '', $jurusanFallback));

                if ($normalizedJurusan === $targetJurusan) {
                    return $subjects;
                }
            }
        }

        return [];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guru = [
            'GURU001' => 'Anisa Kusumawati, S.Pd',
            'GURU002' => 'Fajar Wahyu Pratiwi, S.S',
            'GURU003' => 'Badrus Sulaiman, S.Pd',
            'GURU004' => 'Lutfia Marsalina, S.Pd.I, M.Pd',
            'GURU005' => 'Zainul Arifin, S.Pd',
            'GURU006' => 'Kurnila Putri Islamawati, S.Pd',
            'GURU007' => 'Hendro Suwignyo, ST',
            'GURU008' => 'Wiwik Yuniarsih, S.Pd',
            'GURU009' => 'Erna Qoriah, S.E.',
            'GURU010' => 'Sulistyowati, SS',
            'GURU011' => 'Widodo, S.Pd',
            'GURU012' => 'Laili Ernawati, S.Pd',
            'GURU013' => 'Winartin, S.Pd',
            'GURU014' => 'Mufatiroh, S.Ag',
        ];

        foreach ($guru as $nip => $nama) {
            DB::table('pengguna')->updateOrInsert(
                ['nip' => $nip],
                [
                    'nama' => $nama,
                    'mapel_diampu' => $nama,
                    'no_hp' => null,
                    'status_kepegawaian' => 'PNS',
                    'password' => 'guru123',
                    'role' => 'guru',
                    'id_kelas' => null,
                ]
            );
        }

        $kelasId = DB::table('kelas')->where('nama_kelas', 'XI RPL 2')->value('id_kelas');
        if (!$kelasId) {
            $kelasId = DB::table('kelas')->insertGetId([
                'nama_kelas' => 'XI RPL 2',
                'wali_kelas' => 'Dewi Anjani, S.Pd',
                'jumlah_siswa' => 36,
            ]);
        }

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

        $this->jadwal($idAnisa, $kelasId, 'Senin', 2, '07:40:00', '08:20:00');
        $this->jadwal($idAnisa, $kelasId, 'Senin', 3, '08:20:00', '09:00:00');
        $this->jadwal($idAnisa, $kelasId, 'Senin', 4, '09:00:00', '09:40:00');

        $this->jadwal($idFajar, $kelasId, 'Senin', 5, '10:00:00', '10:35:00');
        $this->jadwal($idFajar, $kelasId, 'Senin', 6, '10:35:00', '11:10:00');

        $this->jadwal($idBadrus, $kelasId, 'Senin', 7, '11:10:00', '11:45:00');
        $this->jadwal($idBadrus, $kelasId, 'Senin', 8, '13:15:00', '13:50:00');
        $this->jadwal($idBadrus, $kelasId, 'Senin', 9, '13:50:00', '14:25:00');
        $this->jadwal($idBadrus, $kelasId, 'Senin', 10, '14:25:00', '15:00:00');

        $this->jadwal($idLutfia, $kelasId, 'Selasa', 1, '07:00:00', '07:40:00');
        $this->jadwal($idLutfia, $kelasId, 'Selasa', 2, '07:40:00', '08:20:00');
        $this->jadwal($idLutfia, $kelasId, 'Selasa', 3, '08:20:00', '09:00:00');

        $this->jadwal($idFajar, $kelasId, 'Selasa', 4, '09:00:00', '09:40:00');
        $this->jadwal($idFajar, $kelasId, 'Selasa', 5, '10:00:00', '10:35:00');

        $this->jadwal($idZainul, $kelasId, 'Selasa', 6, '10:35:00', '11:10:00');
        $this->jadwal($idZainul, $kelasId, 'Selasa', 7, '11:10:00', '11:45:00');

        $this->jadwal($idBadrus, $kelasId, 'Selasa', 8, '13:15:00', '13:50:00');
        $this->jadwal($idBadrus, $kelasId, 'Selasa', 9, '13:50:00', '14:25:00');
        $this->jadwal($idBadrus, $kelasId, 'Selasa', 10, '14:25:00', '15:00:00');

        $this->jadwal($idKurnila, $kelasId, 'Rabu', 1, '07:00:00', '07:40:00');
        $this->jadwal($idKurnila, $kelasId, 'Rabu', 2, '07:40:00', '08:20:00');
        $this->jadwal($idKurnila, $kelasId, 'Rabu', 3, '08:20:00', '09:00:00');
        $this->jadwal($idKurnila, $kelasId, 'Rabu', 4, '09:00:00', '09:40:00');

        $this->jadwal($idHendro, $kelasId, 'Rabu', 5, '10:00:00', '10:35:00');
        $this->jadwal($idHendro, $kelasId, 'Rabu', 6, '10:35:00', '11:10:00');

        $this->jadwal($idWiwik, $kelasId, 'Rabu', 7, '11:10:00', '11:45:00');
        $this->jadwal($idWiwik, $kelasId, 'Rabu', 8, '13:15:00', '13:50:00');

        $this->jadwal($idErna, $kelasId, 'Rabu', 9, '13:50:00', '14:25:00');
        $this->jadwal($idErna, $kelasId, 'Rabu', 10, '14:25:00', '15:00:00');

        $this->jadwal($idSulistyowati, $kelasId, 'Kamis', 1, '07:00:00', '07:40:00');
        $this->jadwal($idSulistyowati, $kelasId, 'Kamis', 2, '07:40:00', '08:20:00');

        $this->jadwal($idWidodo, $kelasId, 'Kamis', 3, '08:20:00', '09:00:00');
        $this->jadwal($idWidodo, $kelasId, 'Kamis', 4, '09:00:00', '09:40:00');

        $this->jadwal($idLaili, $kelasId, 'Kamis', 5, '10:00:00', '10:35:00');
        $this->jadwal($idLaili, $kelasId, 'Kamis', 6, '10:35:00', '11:10:00');

        $this->jadwal($idKurnila, $kelasId, 'Kamis', 7, '11:10:00', '11:45:00');
        $this->jadwal($idKurnila, $kelasId, 'Kamis', 8, '13:15:00', '13:50:00');
        $this->jadwal($idKurnila, $kelasId, 'Kamis', 9, '13:50:00', '14:25:00');
        $this->jadwal($idKurnila, $kelasId, 'Kamis', 10, '14:25:00', '15:00:00');

        $this->jadwal($idWinartin, $kelasId, 'Jumat', 2, '07:30:00', '08:00:00');
        $this->jadwal($idWinartin, $kelasId, 'Jumat', 3, '08:00:00', '08:30:00');
        $this->jadwal($idWinartin, $kelasId, 'Jumat', 4, '08:30:00', '09:00:00');

        $this->jadwal($idMufatiroh, $kelasId, 'Jumat', 5, '09:00:00', '09:30:00');
        $this->jadwal($idMufatiroh, $kelasId, 'Jumat', 6, '09:50:00', '10:20:00');
        $this->jadwal($idMufatiroh, $kelasId, 'Jumat', 7, '10:20:00', '10:50:00');

        $this->jadwal($idBadrus, $kelasId, 'Jumat', 8, '10:50:00', '11:20:00');
        $this->jadwal($idBadrus, $kelasId, 'Jumat', 9, '13:00:00', '13:30:00');
        $this->jadwal($idBadrus, $kelasId, 'Jumat', 10, '13:30:00', '14:00:00');

        $this->jadwal($idAnisa, $kelasId, 'Jumat', 11, '14:00:00', '14:30:00');
        $this->jadwal($idAnisa, $kelasId, 'Jumat', 12, '14:30:00', '15:00:00');
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
