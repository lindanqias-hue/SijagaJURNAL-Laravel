<?php

namespace App\Services;

use App\Models\AbsensiSiswa;
use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Models\KeteranganSiswa;
use Illuminate\Support\Facades\DB;

class DispensasiJurnalService
{
    public function sync(Dispensasi $dispensasi)
    {
        // Hanya dispensasi yang sudah disetujui
        if ($dispensasi->status !== 'Disetujui') {
            return;
        }

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
                    'keterangan' => 'Dispensasi',
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
                        'status' => 'Dispensasi',
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

    private function getJamKe(Dispensasi $dispensasi)
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

    private function getHariIndonesia($tanggal)
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
