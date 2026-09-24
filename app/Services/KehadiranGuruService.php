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
        // Izin dan Sakit tetap dipertahankan
        if (in_array($statusResmi, ['Izin', 'Sakit'], true)) {
            return $statusResmi;
        }

        // Kalau guru sudah mengisi jurnal = Hadir
        if ($jurnalAda) {
            return 'Hadir';
        }

        // Kalau jadwal sudah selesai dan tidak ada jurnal = Tidak Hadir
        if ($jadwalSelesai) {
            return 'Tidak Hadir';
        }

        // Kalau jadwal belum selesai = Menunggu
        return 'Menunggu';
    }

    public function statusUntukJadwal(
        Jadwal $jadwal,
        Carbon $sekarang,
        ?Carbon $tanggalJadwal = null
    ): string {
        $tanggal = ($tanggalJadwal ?? $sekarang)->toDateString();

        /*
         * Cari data kehadiran yang sudah ada.
         * whereDate() digunakan supaya tanggal tetap cocok
         * meskipun database menyimpan jam pada kolom tanggal.
         */
        $catatan = KehadiranGuru::query()
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereDate('tanggal', $tanggal)
            ->first();

        $statusResmi = $catatan?->status;

        /*
         * Cek apakah guru sudah mengisi jurnal
         * untuk jadwal tersebut.
         */
        $jurnalAda = Jurnal::query()
            ->where('id_guru', $jadwal->id_guru)
            ->where('id_kelas', $jadwal->id_kelas)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_ke', $jadwal->jam_ke)
            ->exists();

        /*
         * Jadwal dianggap selesai 5 menit setelah
         * jam selesai yang tercatat di jadwal.
         */
        $selesaiPada = Carbon::parse(
            $tanggal . ' ' . $jadwal->jam_selesai,
            'Asia/Jakarta'
        )->addMinutes(self::GRACE_PERIOD_MINUTES);

        /*
         * Tentukan status otomatis.
         */
        $status = $this->tentukanStatus(
            $jurnalAda,
            $sekarang->greaterThanOrEqualTo($selesaiPada),
            $statusResmi
        );

        /*
         * Update data yang sudah ada.
         * Kalau belum ada, buat data baru.
         *
         * Cara ini mencegah error UNIQUE constraint
         * pada id_jadwal + tanggal.
         */
        if ($catatan) {
            $catatan->update([
                'id_guru' => $jadwal->id_guru,
                'status' => $status,
                'sumber' => 'Sistem',
            ]);
        } else {
            KehadiranGuru::create([
                'id_jadwal' => $jadwal->id_jadwal,
                'tanggal' => $tanggal,
                'id_guru' => $jadwal->id_guru,
                'status' => $status,
                'sumber' => 'Sistem',
            ]);
        }

        return $status;
    }
}