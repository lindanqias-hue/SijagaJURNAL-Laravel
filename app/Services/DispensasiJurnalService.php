<?php

namespace App\Services;

use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Models\AbsensiSiswa;
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

        // Kalau dispensasi Per Jam, cari jurnal
        // yang jamnya masuk dalam rentang dispensasi.
        if ($dispensasi->jenis_dispensasi === 'Per Jam') {

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

            AbsensiSiswa::updateOrCreate(
                [
                    'id_jurnal' => $jurnal->id_jurnal,
                    'id_siswa' => $dispensasi->id_siswa,
                ],
                [
                    'keterangan' => 'Dispensasi',
                    'keterangan_dispensasi' => $dispensasi->alasan,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Buat notifikasi untuk guru yang mengajar
        |--------------------------------------------------------------------------
        */

        $guruIds = DB::table('jadwal')
            ->where('id_kelas', $dispensasi->id_kelas)
            ->whereIn(
                'jam_ke',
                $this->getJamKe($dispensasi)
            )
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
