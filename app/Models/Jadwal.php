<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}