<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrasi data lama: sebelum keterangan_siswa ada, keterangan
     * Dispensasi disimpan di absensi_siswa.keterangan_dispensasi.
     * Baris ini memindahkannya supaya tidak hilang dari tampilan
     * guru-piket yang sekarang membaca dari tabel baru.
     *
     * Sakit & Izin tidak ada apa-apanya untuk dibackfill (kolom itu
     * memang tidak pernah ada untuk keduanya), jadi hanya Dispensasi
     * yang relevan di sini.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('absensi_siswa', 'keterangan_dispensasi')) {
            return;
        }

        $rows = DB::table('absensi_siswa as a')
            ->join('siswa as s', 's.id_siswa', '=', 'a.id_siswa')
            ->join('jurnal as j', 'j.id_jurnal', '=', 'a.id_jurnal')
            ->leftJoin('kelas as k', 'k.id_kelas', '=', 's.id_kelas')
            ->leftJoin('keterangan_siswa as ks', 'ks.id_absensi', '=', 'a.id_absensi')
            ->where('a.keterangan', 'Dispensasi')
            ->whereNotNull('a.keterangan_dispensasi')
            ->whereNull('ks.id')
            ->select(
                'a.id_absensi',
                'a.id_siswa',
                's.nama_siswa',
                'k.nama_kelas',
                'a.keterangan_dispensasi',
                'j.tanggal'
            )
            ->get();

        foreach ($rows as $row) {
            DB::table('keterangan_siswa')->insert([
                'id_absensi' => $row->id_absensi,
                'id_siswa' => $row->id_siswa,
                'nama_siswa' => $row->nama_siswa ?? '-',
                'kelas' => $row->nama_kelas ?? '-',
                'status' => 'Dispensasi',
                'keterangan' => $row->keterangan_dispensasi,
                'tanggal' => $row->tanggal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak ada rollback data yang aman untuk dilakukan otomatis.
    }
};
