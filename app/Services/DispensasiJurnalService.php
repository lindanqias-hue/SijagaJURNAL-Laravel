<?php

namespace App\Services;

use App\Models\AbsensiSiswa;
use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Models\KeteranganSiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DispensasiJurnalService
{
    public function sync(Dispensasi $dispensasi): void
    {
        DB::transaction(function () use ($dispensasi): void {
            $jurnalList = $this->jurnalUntukDispensasi($dispensasi)->get();

            foreach ($jurnalList as $jurnal) {
                $this->terapkanPadaJurnal($dispensasi, $jurnal);
            }

            foreach ($jurnalList as $jurnal) {
                $this->hitungUlangRingkasan($jurnal);
            }
        });

        if ($dispensasi->status !== Dispensasi::STATUS_DISETUJUI) {
            return;
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

    public function syncUntukJurnal(Jurnal $jurnal): void
    {
        $dispensasiDisetujui = Dispensasi::query()
            ->where('id_kelas', $jurnal->id_kelas)
            ->whereDate('tanggal', $jurnal->tanggal)
            ->where('status', Dispensasi::STATUS_DISETUJUI)
            ->whereHas('siswa', fn ($query) => $query->where('id_kelas', $jurnal->id_kelas))
            ->where(function ($query) use ($jurnal): void {
                $query->where('jenis_dispensasi', 'Sehari Penuh')
                    ->orWhere(function ($query) use ($jurnal): void {
                        $query->where('jenis_dispensasi', 'Per Jam')
                            ->where('jam_ke_mulai', '<=', $jurnal->jam_ke)
                            ->where('jam_ke_selesai', '>=', $jurnal->jam_ke);
                    })
                    ->orWhere(function ($query) use ($jurnal): void {
                        $query->where('jenis_dispensasi', 'Per Mapel')
                            ->where('id_guru', $jurnal->id_guru)
                            ->where('jam_ke_mulai', $jurnal->jam_ke);
                    });
            })
            ->get();

        foreach ($dispensasiDisetujui as $dispensasi) {
            $this->terapkanPadaJurnal($dispensasi, $jurnal);
        }

        $this->hitungUlangRingkasan($jurnal->fresh());
    }

    private function terapkanPadaJurnal(Dispensasi $dispensasi, Jurnal $jurnal): void
    {
        if ($dispensasi->status !== Dispensasi::STATUS_DISETUJUI) {
            return;
        }

        $absensi = AbsensiSiswa::updateOrCreate(
            [
                'id_jurnal' => $jurnal->id_jurnal,
                'id_siswa' => $dispensasi->id_siswa,
            ],
            ['keterangan' => 'Dispensasi']
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

    private function jurnalUntukDispensasi(Dispensasi $dispensasi): Builder
    {
        $query = Jurnal::query()
            ->where('id_kelas', $dispensasi->id_kelas)
            ->whereDate('tanggal', $dispensasi->tanggal);

        if ($dispensasi->jenis_dispensasi === 'Per Mapel') {
            return $query->where('id_guru', $dispensasi->id_guru)
                ->where('jam_ke', $dispensasi->jam_ke_mulai);
        }

        if ($dispensasi->jenis_dispensasi === 'Per Jam') {
            return $query->whereBetween('jam_ke', [
                $dispensasi->jam_ke_mulai,
                $dispensasi->jam_ke_selesai,
            ]);
        }

        return $query;
    }

    private function hitungUlangRingkasan(?Jurnal $jurnal): void
    {
        if (! $jurnal) {
            return;
        }

        $jumlahHadir = AbsensiSiswa::query()
            ->where('id_jurnal', $jurnal->id_jurnal)
            ->where('keterangan', 'Hadir')
            ->count();
        $jumlahTidakHadir = AbsensiSiswa::query()
            ->where('id_jurnal', $jurnal->id_jurnal)
            ->whereIn('keterangan', ['Izin', 'Sakit', 'Alpa', 'Dispensasi', 'Tanpa Keterangan'])
            ->count();

        $jurnal->update([
            'jumlah_hadir' => $jumlahHadir,
            'jumlah_tidak_hadir' => $jumlahTidakHadir,
        ]);
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
