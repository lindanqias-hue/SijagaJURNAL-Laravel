<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Satu-satunya sumber kebenaran daftar menu navigasi per role.
 *
 * Kelas ini stateless (hanya static method) sehingga aman dipanggil
 * dari Blade layout maupun dari service container tanpa perlu config.
 *
 * Kontrak item:
 *  - key 'route'   : nama route (wajib). Digunakan untuk build URL.
 *  - key 'anchor'  : opsional. ID section di halaman yang sudah ada
 *                    (admin/wakasek/sekretaris/dashboard). Item dengan
 *                    anchor selalu tampil kalau route-nya ada, karena
 *                    menunjuk ke section yang sudah ada, bukan component
 *                    Livewire baru.
 *  - key 'component': opsional. Nama component Livewire (tanpa prefix
 *                    ⚡). Item ini baru muncul kalau Route::has() DAN
 *                    Livewire::exists() keduanya true.
 *  - key 'group'   : opsional. Label kelompok accordion.
 *  - key 'icon'    : opsional. Emoji/icon kelompok.
 *  - key 'piket'   : opsional. true → item hanya muncul kalau
 *                    $isGuruPiket true.
 */
class NavMenu
{
    /**
     * Daftar menu untuk satu role.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function untukRole(string $role, bool $isGuruPiket = false): array
    {
        return match ($role) {
            'admin' => static::admin(),
            'wakasek' => static::wakasek(),
            'sekretaris' => static::sekretaris(),
            'guru' => static::guru($isGuruPiket),
            default => static::guru($isGuruPiket),
        };
    }

    /**
     * Buat URL navigate untuk satu item menu.
     *
     * Mengembalikan null kalau route belum terdaftar ATAU component
     * belum ada (untuk item bertipe component). Blade layout pakai
     * null ini untuk menyembunyikan link (tidak render href="#").
     */
    public static function url(array $item): ?string
    {
        $route = $item['route'] ?? null;
        if ($route === null || ! Route::has($route)) {
            return null;
        }

        // Item bertipe component: wajib component-nya sudah ada.
        if (isset($item['component']) && ! empty($item['component'])) {
            if (! Livewire::exists($item['component'])) {
                return null;
            }
        }

        $base = route($route);
        $anchor = $item['anchor'] ?? null;

        // Sekretaris memakai ?menu= untuk switch section.
        if ($role = session('role')) {
            if ($role === 'sekretaris' && ! empty($anchor)) {
                return $base.'?menu='.$anchor.'#'.$anchor;
            }
        }

        if (! empty($anchor)) {
            return $base.'#'.$anchor;
        }

        return $base;
    }

    /**
     * Ubah grup menu jadi daftar datar (flatten) untuk mobile section-tabs.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public static function flatten(array $groups): array
    {
        $flat = [];

        foreach ($groups as $group) {
            // Item "langsung" (Dashboard) tidak punya group, tetap dimasukkan.
            if (empty($group['items']) && ! empty($group['route'])) {
                $flat[] = $group;

                continue;
            }

            foreach ($group['items'] as $item) {
                $flat[] = $item;
            }
        }

        return $flat;
    }

    // =========================================================
    // ROLE: ADMIN
    // =========================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function admin(): array
    {
        return [
            // Dashboard langsung (tanpa grup).
            [
                'label' => 'Dashboard Admin',
                'route' => 'admin',
                'anchor' => 'dashboard',
                'component' => 'admin',
            ],
            [
                'label' => 'Data Master',
                'icon' => '🗄️',
                'items' => [
                    ['label' => 'Pengguna',   'route' => 'admin', 'anchor' => 'pengguna'],
                    ['label' => 'Guru',       'route' => 'admin', 'anchor' => 'guru'],
                    ['label' => 'Siswa',      'route' => 'admin', 'anchor' => 'siswa'],
                    ['label' => 'Kelas',      'route' => 'admin', 'anchor' => 'elas'],
                ],
            ],
            [
                'label' => 'Jadwal',
                'icon' => '📅',
                'items' => [
                    ['label' => 'Jadwal Mengajar', 'route' => 'admin', 'anchor' => 'jadwal'],
                    ['label' => 'Jadwal Piket',    'route' => 'admin', 'anchor' => 'jadwal-piket'],
                ],
            ],
            [
                'label' => 'Jurnal',
                'icon' => '📓',
                'items' => [
                    ['label' => 'Jurnal', 'route' => 'admin', 'anchor' => 'jurnal'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'icon' => '📋',
                'items' => [
                    ['label' => 'Dispensasi', 'route' => 'admin', 'anchor' => 'dispensasi'],
                ],
            ],
        ];
    }

    // =========================================================
    // ROLE: WAKASEK
    // =========================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function wakasek(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'wakasek',
                'anchor' => 'dashboard',
                'component' => 'wakasek',
            ],
            [
                'label' => 'Monitoring',
                'icon' => '📊',
                'items' => [
                    ['label' => 'Monitoring Guru',    'route' => 'wakasek', 'anchor' => 'monitoring-guru'],
                    ['label' => 'Monitoring Jurnal',  'route' => 'wakasek', 'anchor' => 'monitoring-jurnal'],
                ],
            ],
            [
                'label' => 'Izin',
                'icon' => '📝',
                'items' => [
                    ['label' => 'Pengajuan Izin Guru', 'route' => 'wakasek', 'anchor' => 'pengajuan-izin'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'icon' => '📋',
                'items' => [
                    ['label' => 'Dispensasi', 'route' => 'wakasek', 'anchor' => 'dispensasi'],
                ],
            ],
            [
                'label' => 'Laporan',
                'icon' => '📈',
                'items' => [
                    ['label' => 'Rekap', 'route' => 'wakasek', 'anchor' => 'rekap'],
                ],
            ],
        ];
    }

    // =========================================================
    // ROLE: GURU
    // =========================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function guru(bool $isGuruPiket): array
    {
        $items = [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'anchor' => 'dashboard',
                'component' => 'dashboard',
            ],
            [
                'label' => 'Jurnal',
                'icon' => '📓',
                'items' => [
                    ['label' => 'Jadwal Saya',  'route' => 'dashboard', 'anchor' => 'jadwal-saya'],
                    ['label' => 'Input Jurnal',  'route' => 'input-jurnal', 'component' => 'input-jurnal'],
                    ['label' => 'Riwayat Saya', 'route' => 'riwayat',      'component' => 'riwayat'],
                ],
            ],
            [
                'label' => 'Notifikasi',
                'icon' => '🔔',
                'items' => [
                    ['label' => 'Notifikasi', 'route' => 'notifikasi', 'component' => 'notifikasi'],
                ],
            ],
            [
                'label' => 'Izin',
                'icon' => '📝',
                'items' => [
                    ['label' => 'Izin Guru', 'route' => 'izin-guru', 'component' => 'izin-guru'],
                ],
            ],
        ];

        if ($isGuruPiket) {
            $items[] = [
                'label' => 'Dispensasi',
                'icon' => '📋',
                'items' => [
                    ['label' => 'Izin & Dispensasi', 'route' => 'dispensasi',        'component' => 'dispensasi'],
                    ['label' => 'Rekapan',            'route' => 'rekap-dispensasi', 'component' => 'rekap-dispensasi'],
                ],
            ];

            $items[] = [
                'label' => 'Piket',
                'icon' => '🛡️',
                'items' => [
                    ['label' => 'Piket Hari Ini', 'route' => 'guru-piket',       'component' => 'guru-piket'],
                    ['label' => 'Kehadiran Siswa', 'route' => 'piket-kehadiran', 'component' => 'piket-kehadiran'],
                ],
            ];
        }

        return $items;
    }

    // =========================================================
    // ROLE: SEKRETARIS
    // =========================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function sekretaris(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'sekretaris',
                'anchor' => 'dashboard',
                'component' => 'sekretaris',
            ],
            [
                'label' => 'Kelas',
                'icon' => '🏫',
                'items' => [
                    ['label' => 'Data Kelas',  'route' => 'sekretaris', 'anchor' => 'data-kelas'],
                    ['label' => 'Kehadiran',   'route' => 'sekretaris', 'anchor' => 'kehadiran'],
                ],
            ],
            [
                'label' => 'Jurnal',
                'icon' => '📓',
                'items' => [
                    ['label' => 'Validasi Jurnal', 'route' => 'sekretaris', 'anchor' => 'validasi-jurnal'],
                ],
            ],
            [
                'label' => 'Tugas',
                'icon' => '✅',
                'items' => [
                    ['label' => 'Tugas Guru Tidak Hadir', 'route' => 'sekretaris', 'anchor' => 'tugas-guru'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'icon' => '📋',
                'items' => [
                    ['label' => 'Surat Dispensasi', 'route' => 'sekretaris', 'anchor' => 'surat-dispensasi'],
                    ['label' => 'Rekap',            'route' => 'sekretaris', 'anchor' => 'rekap'],
                ],
            ],
        ];
    }
}
