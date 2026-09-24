<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\KehadiranGuru;
use Carbon\Carbon;

class KehadiranGuruService
{
    private const GRACE_PERIOD_MINUTES = 5;

    public function tentukanStatus(
        bool $jurnalAda,
        bool $jadwalSelesai,
        ?string $statusResmi = null
    ): string {
        if (in_array($statusResmi, ['Izin', 'Sakit'], true)) {
            return $statusResmi;
        }

        if ($jurnalAda) {
            return 'Hadir';
        }

        return $jadwalSelesai ? 'Tidak Hadir' : 'Menunggu';
    }

    public function statusUntukJadwal(
        Jadwal $jadwal,
        Carbon $sekarang,
        ?Carbon $tanggalJadwal = null
    ): string {
        $tanggal = ($tanggalJadwal ?? $sekarang)->toDateString();
        $catatan = KehadiranGuru::query()
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereDate('tanggal', $tanggal)
            ->first();

        $statusResmi = $catatan?->status;

        $jurnalAda = Jurnal::query()
            ->where('id_guru', $jadwal->id_guru)
            ->where('id_kelas', $jadwal->id_kelas)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_ke', $jadwal->jam_ke)
            ->exists();

        $selesaiPada = Carbon::parse(
            $tanggal.' '.$jadwal->jam_selesai,
            'Asia/Jakarta'
        )->addMinutes(self::GRACE_PERIOD_MINUTES);

        $status = $this->tentukanStatus(
            $jurnalAda,
            $sekarang->greaterThanOrEqualTo($selesaiPada),
            $statusResmi
        );

        KehadiranGuru::updateOrCreate(
            [
                'id_jadwal' => $jadwal->id_jadwal,
                'tanggal' => $tanggal,
            ],
            [
                'id_guru' => $jadwal->id_guru,
                'status' => $status,
                'sumber' => 'Sistem',
            ]
        );

        return $status;
    }
}
