<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyRoleSeeder extends Seeder
{
    public function run(): void
    {
        $kelas = Kelas::query()->firstOrCreate(
            ['nama_kelas' => 'XI RPL 2'],
            ['wali_kelas' => 'Dewi Anjani, S.Pd', 'jumlah_siswa' => 36]
        );

        $pengguna = [
            [
                'nip' => 'DUMMY-ADMIN-001',
                'nama' => 'Drs. Hartono Prasetyo, M.Pd',
                'role' => 'admin',
                'mapel_diampu' => null,
                'no_hp' => '081200000001',
                'status_kepegawaian' => 'PNS',
                'id_kelas' => null,
            ],
            [
                'nip' => 'DUMMY-WAKA-001',
                'nama' => 'Sutrisno, S.Kom',
                'role' => 'wakasek',
                'mapel_diampu' => null,
                'no_hp' => '081200000002',
                'status_kepegawaian' => 'PNS',
                'id_kelas' => null,
            ],
            ['nip' => 'DUMMY-GURU-001', 'nama' => 'Rahmawati, S.Kom', 'mapel_diampu' => 'Basis Data'],
            ['nip' => 'DUMMY-GURU-002', 'nama' => 'Yusuf Hidayat, S.Kom', 'mapel_diampu' => 'Pemrograman Web'],
            ['nip' => 'DUMMY-GURU-003', 'nama' => 'Dewi Anjani, S.Pd', 'mapel_diampu' => 'Matematika'],
            ['nip' => 'DUMMY-GURU-004', 'nama' => 'Bambang Wijaya, S.Pd', 'mapel_diampu' => 'Bahasa Indonesia'],
            ['nip' => 'DUMMY-GURU-005', 'nama' => 'Siti Nurhaliza, S.Pd', 'mapel_diampu' => 'Bahasa Inggris'],
            ['nip' => 'DUMMY-GURU-006', 'nama' => 'Agus Setiawan, S.Kom', 'mapel_diampu' => 'Jaringan Dasar'],
            ['nip' => 'DUMMY-GURU-007', 'nama' => 'Putri Handayani, S.Pd', 'mapel_diampu' => 'Pendidikan Pancasila'],
            ['nip' => 'DUMMY-GURU-008', 'nama' => 'Hendra Gunawan, S.Kom', 'mapel_diampu' => 'Konsentrasi RPL'],
            ['nip' => 'DUMMY-GURU-009', 'nama' => 'Lestari Wahyuni, S.Pd', 'mapel_diampu' => 'Sejarah'],
            ['nip' => 'DUMMY-GURU-010', 'nama' => 'Ahmad Fauzi, S.Kom', 'mapel_diampu' => 'Sistem Komputer'],
        ];

        foreach ($pengguna as $data) {
            $this->upsertPengguna($data + [
                'role' => 'guru',
                'no_hp' => null,
                'status_kepegawaian' => 'PNS',
                'id_kelas' => null,
            ]);
        }

        $this->upsertPengguna([
            'nip' => 'DUMMY-SEKRE-001',
            'nama' => 'Nanda Aurelia',
            'role' => 'sekretaris',
            'mapel_diampu' => null,
            'no_hp' => '081200000003',
            'status_kepegawaian' => null,
            'id_kelas' => $kelas->id_kelas,
        ]);
    }

    private function upsertPengguna(array $data): Pengguna
    {
        $pengguna = Pengguna::query()
            ->where('nip', $data['nip'])
            ->orWhere('nama', $data['nama'])
            ->first();

        if (! $pengguna) {
            $pengguna = new Pengguna;
        }

        $pengguna->fill([
            ...$data,
            'password' => Hash::make('password'),
        ]);
        $pengguna->save();

        return $pengguna;
    }
}
