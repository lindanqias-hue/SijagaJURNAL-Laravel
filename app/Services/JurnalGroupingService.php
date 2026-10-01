<?php

namespace App\Services;

use App\Models\Jurnal;
use Illuminate\Support\Collection;

class JurnalGroupingService
{
    /**
     * Mengelompokkan jurnal berdasarkan guru, kelas, dan tanggal.
     */
    public function group(Collection $jurnals): Collection
    {
        return $jurnals
            ->groupBy(function (Jurnal $jurnal) {
                return implode('|', [
                    $jurnal->id_guru,
                    $jurnal->id_kelas,
                    $jurnal->tanggal?->format('Y-m-d'),
                ]);
            })
            ->map(function (Collection $group) {
                $pertama = $group->sortBy('jam_ke')->first();

                $pertama->jam_ke_selesai = $group
                    ->sortByDesc('jam_ke')
                    ->first()
                    ->jam_ke;

                $pertama->jurnal_group = $group->values();

                return $pertama;
            })
            ->values();
    }
}
