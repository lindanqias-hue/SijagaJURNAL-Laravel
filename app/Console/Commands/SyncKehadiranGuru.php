<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Services\KehadiranGuruService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-kehadiran-guru')]
#[Description('Sinkronkan status kehadiran guru dari jadwal dan jurnal')]
class SyncKehadiranGuru extends Command
{
    public function handle(KehadiranGuruService $kehadiranGuru): int
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $hari = $sekarang->locale('id')->translatedFormat('l');
        $jumlah = 0;

        Jadwal::query()
            ->where('hari', $hari)
            ->get()
            ->each(function (Jadwal $jadwal) use ($kehadiranGuru, $sekarang, &$jumlah): void {
                $kehadiranGuru->statusUntukJadwal($jadwal, $sekarang);
                $jumlah++;
            });

        $this->info("{$jumlah} jadwal guru disinkronkan.");

        return self::SUCCESS;
    }
}
