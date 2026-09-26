<?php

namespace App\Services;

use App\Models\AbsensiSiswa;
use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Models\KeteranganSiswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DispensasiJurnalService
{
    public function sync(Dispensasi $dispensasi): void
    {
        if ($dispensasi->status !== Dispensasi::STATUS_DISETUJUI) {
            return;
        }

        $statusAbsensi = $this->statusAbsensi($dispensasi);

        /*
        |--------------------------------------------------------------------------
        | Cari jurnal yang sesuai
        |--------------------------------------------------------------------------
        | Cocok berdasarkan:
        | - kelas
        | - tanggal
        | - jam ke
        |--------------------------------------------------------------------------
        */

        $jurnalQuery = Jurnal::where(
            'id_kelas',
            $dispensasi->id_kelas
        )
            ->where(
                'tanggal',
                $dispensasi->tanggal
            );

        if ($dispensasi->jenis_dispensasi === 'Per Mapel') {
            $jurnalQuery
                ->where('id_guru', $dispensasi->id_guru)
                ->where('jam_ke', $dispensasi->jam_ke_mulai);
        } elseif ($dispensasi->jenis_dispensasi === 'Per Jam') {

            $jurnalQuery->whereBetween('jam_ke', [
                $dispensasi->jam_ke_mulai,
                $dispensasi->jam_ke_selesai,
            ]);
        }

        $jurnalList = $jurnalQuery->get();

        /*
        |--------------------------------------------------------------------------
        | Update absensi kalau jurnal sudah ada
        |--------------------------------------------------------------------------
        */

        foreach ($jurnalList as $jurnal) {

            $absensi = AbsensiSiswa::updateOrCreate(
                [
                    'id_jurnal' => $jurnal->id_jurnal,
                    'id_siswa' => $dispensasi->id_siswa,
                ],
                [
                    'keterangan' => $statusAbsensi,
                ]
            );

            $siswa = $dispensasi->siswa()->with('kelas')->first();

            if ($siswa) {
                KeteranganSiswa::updateOrCreate(
                    ['id_absensi' => $absensi->id_absensi],
                    [
                        'id_siswa' => $siswa->id_siswa,
                        'nama_siswa' => $siswa->nama_siswa,
                        'kelas' => $siswa->kelas?->nama_kelas ?? '-',
                        'status' => $statusAbsensi,
                        'keterangan' => $dispensasi->alasan,
                        'tanggal' => $dispensasi->tanggal,
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Buat notifikasi untuk guru yang mengajar
        |--------------------------------------------------------------------------
        */

        $guruIds = $dispensasi->jenis_dispensasi === 'Per Mapel'
            ? collect([$dispensasi->id_guru])->filter()
            : DB::table('jadwal')
            ->where('id_kelas', $dispensasi->id_kelas)
            ->where('hari', $this->getHariIndonesia($dispensasi->tanggal))
            ->whereIn('jam_ke', $this->getJamKe($dispensasi))
            ->pluck('id_guru')
            ->unique();

        foreach ($guruIds as $idGuru) {

            DB::table('dispensasi_penerima')->updateOrInsert(
                [
                    'id_dispensasi' => $dispensasi->id_dispensasi,
                    'id_guru' => $idGuru,
                ],
                [
                    'dibaca_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Tentukan jam dispensasi
    |--------------------------------------------------------------------------
    */

    public function statusTerikatUntukJurnal(
        int $idKelas,
        string $tanggal,
        int $jamKe,
        int $idGuru
    ): Collection {
        return Dispensasi::query()
            ->where('id_kelas', $idKelas)
            ->whereDate('tanggal', $tanggal)
            ->where('status', Dispensasi::STATUS_DISETUJUI)
            ->where(function ($query) use ($jamKe, $idGuru): void {
                $query->where('jenis_dispensasi', 'Sehari Penuh')
                    ->orWhere(function ($query) use ($jamKe): void {
                        $query->where('jenis_dispensasi', 'Per Jam')
                            ->where('jam_ke_mulai', '<=', $jamKe)
                            ->where('jam_ke_selesai', '>=', $jamKe);
                    })
                    ->orWhere(function ($query) use ($jamKe, $idGuru): void {
                        $query->where('jenis_dispensasi', 'Per Mapel')
                            ->where('id_guru', $idGuru)
                            ->where('jam_ke_mulai', $jamKe);
                    });
            })
            ->get(['id_siswa', 'jenis_surat', 'alasan'])
            ->mapWithKeys(fn(Dispensasi $dispensasi): array => [
                $dispensasi->id_siswa => [
                    'status' => $this->statusAbsensi($dispensasi),
                    'keterangan' => $dispensasi->alasan,
                ],
            ]);
    }

    private function statusAbsensi(Dispensasi $dispensasi): string
    {
        return $dispensasi->jenis_surat === Dispensasi::JENIS_SURAT_IZIN
            ? 'Izin'
            : 'Dispensasi';
    }

    /** @return array<int, int> */
    private function getJamKe(Dispensasi $dispensasi): array
    {
        // Sehari penuh → ambil semua jam pada hari tersebut
        if ($dispensasi->jenis_dispensasi === 'Sehari Penuh') {

            return DB::table('jadwal')
                ->where('id_kelas', $dispensasi->id_kelas)
                ->where('hari', $this->getHariIndonesia($dispensasi->tanggal))
                ->pluck('jam_ke')
                ->unique()
                ->values()
                ->toArray();
        }

        if ($dispensasi->jenis_dispensasi === 'Per Mapel') {
            return [(int) $dispensasi->jam_ke_mulai];
        }

        // Per Jam
        return range(
            (int) $dispensasi->jam_ke_mulai,
            (int) $dispensasi->jam_ke_selesai
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ubah tanggal menjadi nama hari Indonesia
    |--------------------------------------------------------------------------
    */

    private function getHariIndonesia(string|Carbon|\DateTimeInterface $tanggal): string
    {
        $hari = date('l', strtotime($tanggal));

        return [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ][$hari] ?? $hari;
    }
}
