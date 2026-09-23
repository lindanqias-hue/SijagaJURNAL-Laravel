<?php

namespace Database\Seeders;

use App\Models\AbsensiSiswa;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\KeteranganSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyAbsensiSiswaSeeder extends Seeder
{
    public function run(): void
    {
        $kelas = Kelas::where('nama_kelas', 'XI RPL 2')->firstOrFail();
        $siswa = Siswa::where('id_kelas', $kelas->id_kelas)->orderBy('id_siswa')->get();

        if ($siswa->count() < 5) {
            throw new \RuntimeException('Minimal lima siswa XI RPL 2 diperlukan untuk dummy absensi.');
        }

        $contexts = [
            ['tanggal' => '2026-09-22', 'hari' => 'Selasa', 'jam_ke' => 2, 'guru' => 'Lutfia Marsalina, S.Pd.I, M.Pd'],
            ['tanggal' => '2026-09-22', 'hari' => 'Selasa', 'jam_ke' => 3, 'guru' => 'Lutfia Marsalina, S.Pd.I, M.Pd'],
            ['tanggal' => '2026-09-23', 'hari' => 'Rabu', 'jam_ke' => 4, 'guru' => 'Kurnila Putri Islamawati, S.Pd'],
            ['tanggal' => '2026-09-23', 'hari' => 'Rabu', 'jam_ke' => 5, 'guru' => 'Hendro Suwignyo, ST'],
            ['tanggal' => '2026-09-23', 'hari' => 'Rabu', 'jam_ke' => 6, 'guru' => 'Hendro Suwignyo, ST'],
            ['tanggal' => '2026-09-23', 'hari' => 'Rabu', 'jam_ke' => 7, 'guru' => 'Wiwik Yuniarsih, S.Pd'],
        ];

        foreach ($contexts as $context) {
            $guru = Pengguna::where('nama', $context['guru'])->firstOrFail();
            $jadwalAda = DB::table('jadwal')
                ->where('id_guru', $guru->id_pengguna)
                ->where('id_kelas', $kelas->id_kelas)
                ->where('hari', $context['hari'])
                ->where('jam_ke', $context['jam_ke'])
                ->exists();

            if (! $jadwalAda) {
                throw new \RuntimeException("Jadwal dummy tidak ditemukan untuk {$context['hari']} jam {$context['jam_ke']}.");
            }

            $jurnal = Jurnal::firstOrCreate(
                [
                    'id_guru' => $guru->id_pengguna,
                    'id_kelas' => $kelas->id_kelas,
                    'tanggal' => $context['tanggal'],
                    'jam_ke' => $context['jam_ke'],
                ],
                [
                    'materi' => 'Dummy testing absensi siswa',
                    'status_kehadiran_guru' => 'Hadir',
                    'status_validasi' => 'Divalidasi',
                    'catatan_validasi' => 'Data dummy testing absensi siswa',
                ]
            );

            $statusByStudent = $this->statusUntukContext($context['jam_ke'], $siswa->count());

            foreach ($siswa as $index => $student) {
                $status = $statusByStudent[$index] ?? 'Hadir';
                $absensi = AbsensiSiswa::updateOrCreate(
                    ['id_jurnal' => $jurnal->id_jurnal, 'id_siswa' => $student->id_siswa],
                    ['keterangan' => $status]
                );

                if (in_array($status, ['Sakit', 'Izin', 'Dispensasi'], true)) {
                    KeteranganSiswa::updateOrCreate(
                        ['id_absensi' => $absensi->id_absensi],
                        [
                            'id_siswa' => $student->id_siswa,
                            'nama_siswa' => $student->nama_siswa,
                            'kelas' => $kelas->nama_kelas,
                            'status' => $status,
                            'keterangan' => "Dummy {$status} untuk pengujian jam ke-{$context['jam_ke']}",
                            'tanggal' => $context['tanggal'],
                        ]
                    );
                }
            }

            $statuses = AbsensiSiswa::where('id_jurnal', $jurnal->id_jurnal)->pluck('keterangan');
            $jumlahHadir = $statuses->filter(fn ($status) => $status === 'Hadir')->count();
            $jurnal->update([
                'jumlah_hadir' => $jumlahHadir,
                'jumlah_tidak_hadir' => $siswa->count() - $jumlahHadir,
            ]);
        }

        $this->command->info('Dummy absensi siswa XI RPL 2 berhasil dibuat/diperbarui.');
    }

    private function statusUntukContext(int $jamKe, int $jumlahSiswa): array
    {
        $statuses = array_fill(0, $jumlahSiswa, 'Hadir');

        if ($jamKe === 2) {
            $statuses[0] = 'Sakit';
            $statuses[1] = 'Izin';
            $statuses[2] = 'Dispensasi';
            $statuses[3] = 'Tanpa Keterangan';
        } elseif ($jamKe === 3) {
            $statuses[4] = 'Izin';
        } elseif ($jamKe === 4) {
            $statuses[1] = 'Izin';
            $statuses[2] = 'Tanpa Keterangan';
            $statuses[5] = 'Sakit';
        } elseif ($jamKe === 5) {
            $statuses[1] = 'Hadir';
            $statuses[3] = 'Sakit';
            $statuses[5] = 'Sakit';
        } elseif ($jamKe === 6) {
            $statuses[2] = 'Dispensasi';
            $statuses[3] = 'Hadir';
            $statuses[5] = 'Sakit';
        } elseif ($jamKe === 7) {
            $statuses[4] = 'Tanpa Keterangan';
            $statuses[5] = 'Sakit';
        }

        return $statuses;
    }
}
