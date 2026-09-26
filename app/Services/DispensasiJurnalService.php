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

        $jurnalQuery = Jurnal::where('id_kelas', $dispensasi->id_kelas)
            ->where('tanggal', $dispensasi->tanggal);

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

        DB::transaction(function () use ($jurnalList, $dispensasi, $statusAbsensi): void {
            foreach ($jurnalList as $jurnal) {
                $absensi = AbsensiSiswa::updateOrCreate(
                    [
                        'id_jurnal' => $jurnal->id_jurnal,
                        'id_siswa'  => $dispensasi->id_siswa,
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
                            'id_siswa'   => $siswa->id_siswa,
                            'nama_siswa' => $siswa->nama_siswa,
                            'kelas'      => $siswa->kelas?->nama_kelas ?? '-',
                            'status'     => $statusAbsensi,
                            'keterangan' => $dispensasi->alasan,
                            'tanggal'    => $dispensasi->tanggal,
                        ]
                    );
                }

                $this->hitungUlangRingkasan($jurnal);
            }
        });

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
                    'id_guru'       => $idGuru,
                ],
                [
                    'dibaca_at'  => null,
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
            ->whereHas('siswa', fn($query) => $query->where('id_kelas', $jurnal->id_kelas))
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

        $statusAbsensi = $this->statusAbsensi($dispensasi);

        $absensi = AbsensiSiswa::updateOrCreate(
            [
                'id_jurnal' => $jurnal->id_jurnal,
                'id_siswa'  => $dispensasi->id_siswa,
            ],
            ['keterangan' => $statusAbsensi]
        );

        $siswa = $dispensasi->siswa()->with('kelas')->first();

        if ($siswa) {
            KeteranganSiswa::updateOrCreate(
                ['id_absensi' => $absensi->id_absensi],
                [
                    'id_siswa'   => $siswa->id_siswa,
                    'nama_siswa' => $siswa->nama_siswa,
                    'kelas'      => $siswa->kelas?->nama_kelas ?? '-',
                    'status'     => $statusAbsensi,
                    'keterangan' => $dispensasi->alasan,
                    'tanggal'    => $dispensasi->tanggal,
                ]
            );
        }
    }

    private function hitungUlangRingkasan(?Jurnal $jurnal): void
    {
        if (!$jurnal) {
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
            'jumlah_hadir'       => $jumlahHadir,
            'jumlah_tidak_hadir' => $jumlahTidakHadir,
        ]);
    }

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
                    'status'     => $this->statusAbsensi($dispensasi),
                    'keterangan' => $dispensasi->alasan,
                ],
            ]);
    }

    private function statusAbsensi(Dispensasi $dispensasi): string
    {
        // Menambahkan pemetaan untuk Surat Sakit
        if ($dispensasi->jenis_surat === 'Sakit') {
            return 'Sakit';
        }

        return $dispensasi->jenis_surat === Dispensasi::JENIS_SURAT_IZIN
            ? 'Izin'
            : 'Dispensasi';
    }

    /** @return array<int, int> */
    private function getJamKe(Dispensasi $dispensasi): array
    {
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

        return range(
            (int) $dispensasi->jam_ke_mulai,
            (int) $dispensasi->jam_ke_selesai
        );
    }

    private function getHariIndonesia(string|Carbon|\DateTimeInterface $tanggal): string
    {
        $hari = date('l', strtotime($tanggal));

        return [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ][$hari] ?? $hari;
    }
}
