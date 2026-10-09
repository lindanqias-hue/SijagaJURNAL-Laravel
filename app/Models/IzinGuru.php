<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class IzinGuru extends Model
{
    protected $table = 'izin_guru';

    protected $primaryKey = 'id_izin';

    public $timestamps = true;

    protected $fillable = [
        'id_guru',
        'id_kelas',
        'tanggal',
        'jam_ke_mulai',
        'jam_ke_selesai',
        'jenis_izin',
        'tugas_per_jam',
        'status',
        'id_validator',
        'tanggal_validasi',
        'catatan_validasi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tugas_per_jam' => 'array',
        'tanggal_validasi' => 'datetime',
    ];

    public const JENIS_IZIN = ['Izin', 'Sakit', 'Dinas Luar Kota', 'Cuti'];

    public function guru()
    {
        return $this->belongsTo(Pengguna::class, 'id_guru', 'id_pengguna');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id_kelas');
    }

    public function validator()
    {
        return $this->belongsTo(Pengguna::class, 'id_validator', 'id_pengguna');
    }

    /**
     * Check if guru has approved izin for specific tanggal + jam_ke
     * Used by Orang 1 & 2
     */
    public static function isGuruIzin(int $guruId, string $tanggal, int $jamKe): bool
    {
        return self::where('id_guru', $guruId)
            ->where('tanggal', $tanggal)
            ->where('jam_ke_mulai', '<=', $jamKe)
            ->where('jam_ke_selesai', '>=', $jamKe)
            ->where('status', 'Disetujui')
            ->exists();
    }

    /**
     * Get izin for wakasek validation (grouped by actual schedule)
     */
    public static function getForWakasekValidation(): Collection
    {
        return self::with(['guru', 'kelas'])
            ->where('status', 'Menunggu')
            ->orderBy('tanggal')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->groupBy(function ($izin) {
                return $izin->guru->id_pengguna.'-'.$izin->tanggal->format('Y-m-d');
            })
            ->map(function ($group) {
                // Sort by jam_ke_mulai within group
                return $group->sortBy('jam_ke_mulai')->values();
            });
    }
}
