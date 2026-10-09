<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $primaryKey = 'id_jadwal';

    public $timestamps = false;

    protected $fillable = [
        'id_guru',
        'id_kelas',
        'hari',
        'jam_ke',
        'jam_mulai',
        'jam_selesai',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(
            Kelas::class,
            'id_kelas',
            'id_kelas'
        );
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_guru',
            'id_pengguna'
        );
    }

    public function jurnalHariIni(): HasMany
    {
        return $this->hasMany(
            Jurnal::class,
            'id_kelas',
            'id_kelas'
        )->whereColumn('jurnal.id_guru', 'jadwal.id_guru')
            ->whereColumn('jurnal.jam_ke', 'jadwal.jam_ke');
    }
}
