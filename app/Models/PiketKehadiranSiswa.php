<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketKehadiranSiswa extends Model
{
    protected $table = 'piket_kehadiran_siswa';

    protected $primaryKey = 'id_piket_kehadiran';

    public $timestamps = true;

    public const STATUS = ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Tanpa Keterangan'];

    protected $fillable = [
        'id_kelas',
        'id_siswa',
        'tanggal',
        'jam_ke',
        'status',
        'keterangan',
        'id_guru_piket',
        'sumber',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id_kelas');
    }

    public function guruPiket()
    {
        return $this->belongsTo(Pengguna::class, 'id_guru_piket', 'id_pengguna');
    }

    /**
     * Ambil catatan piket untuk satu kelas + tanggal + jam.
     *
     * @return array<int, array{status: string, keterangan: string}>
     */
    public static function untukKelas(int $idKelas, string $tanggal, int $jamKe): array
    {
        return self::query()
            ->where('id_kelas', $idKelas)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_ke', $jamKe)
            ->get()
            ->mapWithKeys(fn (self $row): array => [
                (int) $row->id_siswa => [
                    'status' => $row->status,
                    'keterangan' => $row->keterangan ?? '',
                ],
            ])
            ->all();
    }
}
