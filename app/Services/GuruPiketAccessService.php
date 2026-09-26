<?php

namespace App\Services;

use App\Models\GuruPiket;
use App\Models\JadwalPiket;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class GuruPiketAccessService
{
    public function bertugasHariIni(int $idGuru): bool
    {
        return $this->bertugasPadaTanggal($idGuru, Carbon::now('Asia/Jakarta'));
    }

    public function bertugasPadaTanggal(int $idGuru, CarbonInterface $tanggal): bool
    {
        $hari = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][$tanggal->isoWeekday()];

        return GuruPiket::query()
            ->where('id_pengguna', $idGuru)
            ->where('hari', $hari)
            ->where('aktif', true)
            ->exists()
            || JadwalPiket::query()
                ->where('id_guru', $idGuru)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->where('status', 'Aktif')
                ->exists();
    }
}
