<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengguna extends Model
{
    protected $table = 'pengguna';

    protected $primaryKey = 'id_pengguna';

    public $timestamps = false;

    protected $fillable = [
        'nip',
        'nama',
        'mapel_diampu',
        'no_hp',
        'status_kepegawaian',
        'password',
        'role',
        'id_kelas',
    ];
}