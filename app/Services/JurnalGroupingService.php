<?php

namespace App\Services;

use App\Models\IzinGuru;
use App\Models\Jurnal;
use Illuminate\Support\Collection;

class JurnalGroupingService
{
    /**
     * Group journals by guru → kelas → tanggal → journals[]
     *
     * @param  Collection|Jurnal[]  $journals
     * @return Collection Grouped: guru_id → kelas_id → tanggal → journals[]
     */
    public function groupByGuruKelasTanggal(Collection $journals): Collection
    {
        return $journals
            ->groupBy('id_guru')
            ->map(function ($byGuru) {
                return $byGuru
                    ->groupBy('id_kelas')
                    ->map(function ($byKelas) {
                        return $byKelas
                            ->groupBy(function ($jurnal) {
                                return $jurnal->tanggal->format('Y-m-d');
                            })
                            ->map(function ($byTanggal) {
                                return $byTanggal->sortBy('jam_ke')->values();
                            });
                    });
            });
    }

    /**
     * Get grouped data for Wakasek validation
     * Returns: [guru_id => [kelas_id => [tanggal => [journals]]]]
     */
    public function getForWakasekValidation(): Collection
    {
        $journals = Jurnal::with(['guru', 'kelas'])
            ->where('adalah_pengajuan_izin', false)
            ->orderBy('id_guru')
            ->orderBy('id_kelas')
            ->orderBy('tanggal')
            ->orderBy('jam_ke')
            ->get();

        return $this->groupByGuruKelasTanggal($journals);
    }

    /**
     * Check if guru has izin for specific tanggal + jam_ke
     */
    public function isGuruIzin(int $guruId, string $tanggal, int $jamKe): bool
    {
        // Uses IzinGuru model (Orang 3)
        if (! class_exists(IzinGuru::class)) {
            return false;
        }

        return IzinGuru::where('id_guru', $guruId)
            ->where('tanggal', $tanggal)
            ->where('jam_ke_mulai', '<=', $jamKe)
            ->where('jam_ke_selesai', '>=', $jamKe)
            ->where('status', 'Disetujui')
            ->exists();
    }
}
