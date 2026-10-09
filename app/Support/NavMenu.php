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
 *  - key 'group'   : opsional. Label kelompok (section) pada
 *                    sidebar flat.
 *  - key 'icon'    : opsional. Kunci ikon di App\Support\NavIcon.
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
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Data Master',
                'items' => [
                    ['label' => 'Pengguna',   'route' => 'admin', 'anchor' => 'pengguna', 'icon' => 'people'],
                    ['label' => 'Guru',       'route' => 'admin', 'anchor' => 'guru',     'icon' => 'person-badge'],
                    ['label' => 'Siswa',      'route' => 'admin', 'anchor' => 'siswa',    'icon' => 'person'],
                    ['label' => 'Kelas',      'route' => 'admin', 'anchor' => 'kelas',    'icon' => 'building'],
                ],
            ],
            [
                'label' => 'Jadwal',
                'items' => [
                    ['label' => 'Jadwal Mengajar', 'route' => 'admin', 'anchor' => 'jadwal',       'icon' => 'calendar-week'],
                    ['label' => 'Jadwal Piket',    'route' => 'admin', 'anchor' => 'jadwal-piket', 'icon' => 'calendar-check'],
                ],
            ],
            [
                'label' => 'Jurnal',
                'items' => [
                    ['label' => 'Jurnal', 'route' => 'admin', 'anchor' => 'jurnal', 'icon' => 'journal-bookmark'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'items' => [
                    ['label' => 'Dispensasi', 'route' => 'admin', 'anchor' => 'dispensasi', 'icon' => 'clipboard-check'],
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
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Monitoring',
                'items' => [
                    ['label' => 'Monitoring Guru',   'route' => 'wakasek', 'anchor' => 'monitoring-guru',  'icon' => 'people-fill'],
                    ['label' => 'Monitoring Jurnal', 'route' => 'wakasek', 'anchor' => 'monitoring-jurnal', 'icon' => 'journal-text'],
                ],
            ],
            [
                'label' => 'Izin',
                'items' => [
                    ['label' => 'Pengajuan Izin Guru', 'route' => 'wakasek', 'anchor' => 'pengajuan-izin', 'icon' => 'pencil-square'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'items' => [
                    ['label' => 'Dispensasi', 'route' => 'wakasek-dispensasi', 'component' => 'wakasek-dispensasi', 'icon' => 'clipboard-check'],
                ],
            ],
            [
                'label' => 'Laporan',
                'items' => [
                    ['label' => 'Rekap', 'route' => 'wakasek', 'anchor' => 'rekap', 'icon' => 'bar-chart'],
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
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Jurnal',
                'items' => [
                    ['label' => 'Jadwal Saya',  'route' => 'dashboard',   'anchor' => 'jadwal-saya', 'icon' => 'calendar-date'],
                    ['label' => 'Input Jurnal',  'route' => 'input-jurnal', 'component' => 'input-jurnal', 'icon' => 'pencil'],
                    ['label' => 'Riwayat Saya', 'route' => 'riwayat',      'component' => 'riwayat',  'icon' => 'clock-history'],
                ],
            ],
            [
                'label' => 'Notifikasi',
                'items' => [
                    ['label' => 'Notifikasi', 'route' => 'notifikasi', 'component' => 'notifikasi', 'icon' => 'bell'],
                ],
            ],
            [
                'label' => 'Izin',
                'items' => [
                    ['label' => 'Izin Guru', 'route' => 'izin-guru', 'component' => 'izin-guru', 'icon' => 'pencil-square'],
                ],
            ],
        ];

        if ($isGuruPiket) {
            $items[] = [
                'label' => 'Dispensasi',
                'items' => [
                    ['label' => 'Izin & Dispensasi', 'route' => 'dispensasi',        'component' => 'dispensasi',        'icon' => 'clipboard-check'],
                    ['label' => 'Rekapan',            'route' => 'rekap-dispensasi', 'component' => 'rekap-dispensasi', 'icon' => 'bar-chart'],
                ],
            ];

            $items[] = [
                'label' => 'Piket',
                'items' => [
                    ['label' => 'Piket Hari Ini', 'route' => 'guru-piket',       'component' => 'guru-piket',       'icon' => 'shield'],
                    ['label' => 'Kehadiran Siswa', 'route' => 'piket-kehadiran', 'component' => 'piket-kehadiran', 'icon' => 'list-check'],
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
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Kelas',
                'items' => [
                    ['label' => 'Data Kelas',  'route' => 'sekretaris', 'anchor' => 'data-kelas',  'icon' => 'building'],
                    ['label' => 'Kehadiran',   'route' => 'sekretaris', 'anchor' => 'kehadiran',   'icon' => 'check-circle'],
                ],
            ],
            [
                'label' => 'Jurnal',
                'items' => [
                    ['label' => 'Validasi Jurnal', 'route' => 'sekretaris', 'anchor' => 'validasi-jurnal', 'icon' => 'check2-square'],
                ],
            ],
            [
                'label' => 'Tugas',
                'items' => [
                    ['label' => 'Tugas Guru Tidak Hadir', 'route' => 'sekretaris', 'anchor' => 'tugas-guru', 'icon' => 'clipboard-check'],
                ],
            ],
            [
                'label' => 'Dispensasi',
                'items' => [
                    ['label' => 'Surat Dispensasi', 'route' => 'sekretaris', 'anchor' => 'surat-dispensasi', 'icon' => 'envelope'],
                    ['label' => 'Rekap',            'route' => 'sekretaris', 'anchor' => 'rekap',            'icon' => 'bar-chart'],
                ],
            ],
        ];
    }
}
