<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeekendGuruPiketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (
            [
                ['GURU006', 'Sabtu'],
                ['GURU007', 'Minggu'],
            ] as [$nip, $hari]
        ) {
            $idPengguna = Pengguna::query()
                ->where('nip', $nip)
                ->where('role', 'guru')
                ->value('id_pengguna');

            if (! $idPengguna) {
                continue;
            }

            DB::table('guru_piket')->updateOrInsert(
                ['id_pengguna' => $idPengguna, 'hari' => $hari],
                [
                    'jam_mulai' => '07:00:00',
                    'jam_selesai' => '15:00:00',
                    'aktif' => true,
                ]
            );
        }
    }
}
