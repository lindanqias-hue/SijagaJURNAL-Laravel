# Redesign Navigasi: Sidebar Flat + Topbar Terpadu

## Context

Navigasi saat ini: sidebar accordion (Alpine `navAccordion`), ikon emoji, jam di sidebar (desktop) + topbar mobile terpisah, tanpa topbar desktop, pill strip di semua halaman mobile.

Target: tampilan admin dashboard modern standar — sidebar flat dengan ikon SVG inline, topbar slim terpadu (judul halaman + jam), drawer offcanvas mobile, pill strip hanya di halaman ber-section.

## Locked Decisions

- **Ikon**: inline SVG via class baru `App\Support\NavIcon` (path data dari Bootstrap Icons, lisensi MIT, `viewBox="0 0 16 16"`, `fill="currentColor"`). Tanpa package baru.
- **Bentuk data menu tetap grouped** (section => items) sehingga kontrak `NavMenu::url()/flatten()/untukRole()` tidak berubah. Yang flat adalah **rendering**-nya (label section + item selalu terlihat, tanpa accordion).
- **Dedup wakasek**: grup "Dispensasi" wakasek jadi satu item → route `wakasek-dispensasi` + component `wakasek-dispensasi` (item anchor `dispensasi` dihapus).
- **Topbar**: `--topbar-h: 60px`, muncul di semua breakpoint; hamburger `d-lg-none`; judul halaman (derived JS, overridable via `[data-page-title]`); jam + tanggal pindah ke sini.
- **Sidebar**: `--sidebar-w: 250px`, sticky full-height di lg+, tanpa collapse, jadi offcanvas drawer di < lg.
- **Pill strip mobile** (`.section-tabs`) hanya render kalau route saat ini punya ≥1 item menu dengan anchor non-kosong (admin, wakasek, sekretaris, dashboard).
- **Active state**: indikator bar kiri via CSS `::before`; `.dot` dihapus.
- **Print mode** (`?print`) dan `layouts.guest` tidak disentuh.

## Tasks

### 1. Buat `app/Support/NavIcon.php`

```php
public static function svg(string $key, array $attrs = []): string
// mengembalikan string <svg ...>...</svg> lengkap
protected static function paths(): array
// map key => markup inner Bootstrap Icons (<path .../>)
```

- Fallback ke ikon default kalau key tidak ditemukan.
- Izinkan atribut tambahan (class, aria-hidden) digabung ke `<svg>`.

**Tabel mapping key → Bootstrap Icons name:**

| Key | Icon | Key | Icon |
|-----|------|-----|------|
| dashboard | speedometer2 | people | people |
| person-badge | person-badge | person | person |
| building | building | calendar-week | calendar-week |
| calendar-check | calendar-check | journal-bookmark | journal-bookmark |
| clipboard-check | clipboard-check | people-fill | people-fill |
| journal-text | journal-text | pencil-square | pencil-square |
| bar-chart | bar-chart | calendar-date | calendar-date |
| pencil | pencil | clock-history | clock-history |
| bell | bell | shield | shield |
| list-check | list-check | check-circle | check-circle |
| envelope | envelope | check2-square | check2-square |
| exclamation-circle | exclamation-circle | | |

### 2. Update `app/Support/NavMenu.php`

- Tambah `'icon' => '<key>'` pada setiap item (mapping: admin → dashboard/people/person-badge/person/building/calendar-week/calendar-check/journal-bookmark/clipboard-check; wakasek → dashboard/people-fill/journal-text/pencil-square/clipboard-check/bar-chart; guru → dashboard/calendar-date/pencil/clock-history/bell/pencil-square/clipboard-check/bar-chart/shield/list-check; sekretaris → dashboard/building/check-circle/check2-square/clipboard-check/envelope/bar-chart).
- Dedup wakasek: hapus item `['label'=>'Dispensasi','route'=>'wakasek','anchor'=>'dispensasi']`, sisakan satu item `'label'=>'Dispensasi','route'=>'wakasek-dispensasi','component'=>'wakasek-dispensasi','icon'=>'clipboard-check'`.
- Signature + return type `url()`, `flatten()`, `untukRole()` tetap. Update PHPDoc kontrak: `icon` = key NavIcon (bukan emoji).

### 3. Rewrite `resources/views/layouts/app.blade.php` (cabang non-print)

Struktur baru:

```
.app-wrapper
  header.app-topbar
    button.btn-toggle d-lg-none (data-bs-toggle="offcanvas", data-bs-target="#sidebarNav")
    div[data-topbar-title]
    div.layout-clock > time[data-layout-clock-time] + time[data-layout-clock-date]
  aside.sidebar.offcanvas-lg.offcanvas-start#sidebarNav
    .sidebar-brand (logo + SIJAGA + btn-close d-lg-none) — jam desktop dihapus
    nav.sidebar-nav
      @foreach $groups: item langsung → a.nav-link(icon+label)
                     grup → div.nav-section-label + a.nav-link per item (icon+label)
    .sidebar-user (profile-box + btn-logout) — tetap
  .content-col
    main.main-content
      @if ($isSectionPage) nav.section-tabs.d-lg-none[data-section-nav] (pill markup tetap)
      {{ $slot }}
```

- Hitung `$groups` dan `$flat` sekali di awal cabang non-print.
- Derivasi halaman section:
  ```php
  $currentRoute = request()->route()?->getName();
  $isSectionPage = $currentRoute !== null
      && collect($flat)->where('route', $currentRoute)->where('anchor', '!=', '')->isNotEmpty();
  ```
- **JS**:
  - Hapus fungsi `navAccordion()` dan atribut `x-data`/`x-init` di `<nav>`.
  - IIFE sidebar active-link: tetap (hashchange/pageshow/livewire:navigated), hapus handling `.dot`, **tambah** update judul topbar: `[data-page-title]` → teks active `.nav-link` → `'SIJAGA'`.
  - Handler `sekretaris-menu-berubah`: tetap update active link + **juga update judul topbar**.
  - IIFE jam dan IIFE section-tabs: tidak berubah.
- Pertahankan: `<meta name="role">`, cabang print, semua `data-nav-anchor` / `data-route-active` / `data-nav-menu` / `data-nav-path`, `aria-current`.

### 4. Update `public/assets/css/style.css`

- `:root` tambah `--topbar-h: 60px; --sidebar-w: 250px;`
- Baru: `.app-topbar` (sticky top 0, z-index 1030, tinggi var(--topbar-h), bg #fff, border-bottom, flex, gap), `.topbar-title` (fw-700, truncate), `.app-topbar .btn-toggle` (pindahkan style dari `.topbar-mobile .btn-toggle`).
- `.layout-clock` jadi horizontal (row, align-center, gap, margin-left auto); hapus `.layout-clock-mobile`, `.layout-clock-desktop`, `.topbar-mobile`.
- `.sidebar, .offcanvas-lg.sidebar`: pakai `var(--sidebar-w)`; di `@media (min-width: 992px)` tambah `position: sticky; top: 0; height: 100vh;`.
- `.sidebar-nav`: tambah `.nav-section-label` (uppercase, 10.5px, letter-spacing .1em, color rgba(255,255,255,.45), padding 14px 12px 6px), `.nav-icon` (18px, flex-shrink 0, svg 100%); `.nav-link { position: relative; }`; active indicator: `.sidebar-nav .nav-link.active::before` (bar 3px kiri, #60a5fa, rounded).
- Hapus: `.dot`, semua `.nav-group*` (accordion, baris ~595–677).
- `.section-tabs { top: var(--topbar-h); }`; hapus `top: 66px` di media query.
- `.role-menu-card .role-menu-icon svg { width: 22px; height: 22px; }`.

### 5. Konversi ikon kartu menu cepat → `NavIcon::svg()`

- `⚡dashboard.blade.php`: array `$extraMenus` (Izin Guru→pencil-square, Kehadiran Siswa→list-check) + 7 entity keras (Jadwal Saya→calendar-date, Input Jurnal→pencil, Riwayat Saya→clock-history, Notifikasi→bell, Izin & Dispensasi→clipboard-check, Piket Hari Ini→shield, Rekapan→bar-chart).
- `⚡wakasek.blade.php`: 9 entity (Pengajuan Izin Guru→pencil-square, Monitoring Guru→people-fill, Monitoring Jurnal→journal-text, Dispensasi→clipboard-check, Rekap & Laporan→bar-chart, Riwayat Jurnal→journal-text, Guru Belum Mengisi→exclamation-circle, Monitoring Kehadiran Guru→check-circle, Rekap Per Kelas→building).
- `⚡sekretaris.blade.php`: 6 entity (Data Kelas→building, Validasi Jurnal→check2-square, Kehadiran→check-circle, Tugas Guru Tidak Hadir→clipboard-check, Surat Dispensasi→envelope, Rekap→bar-chart).
- Tambah atribut `data-page-title` pada heading utama `⚡jurnal-detail.blade.php` dan `⚡dispensasi-detail.blade.php` (halaman tanpa link sidebar agar judul topbar benar).

### 6. Verifikasi

- `vendor/bin/pint --format agent`
- `php artisan view:clear && php artisan view:cache`
- `php artisan test` — semua harus tetap hijau
- Smoke test manual per role (admin, wakasek, sekretaris, guru, guru-piket): nav flat, ikon SVG, indikator aktif, judul + jam di topbar, drawer mobile, pill strip muncul/hilang sesuai halaman, `?menu=` sekretaris + event `sekretaris-menu-berubah`, navigasi hash di halaman hub, mode print.

## Contracts Preserved

`NavMenu::url(array): ?string`, `NavMenu::flatten(array): array`, `NavMenu::untukRole(string, bool): array`; `data-nav-anchor`, `data-route-active`, `data-nav-menu`, `data-nav-path`; `.nav-link`, `.tab-pill`, `.section-tabs`, `[data-section-nav]`; event `sekretaris-menu-berubah`; `<meta name="role">`; `data-layout-clock-time` / `data-layout-clock-date`.

## Risks

- Penghapusan `.dot`: hanya JS layout yang mereferensinya (terverifikasi) — aman.
- Pill strip tidak lagi muncul di halaman standalone (input-jurnal, riwayat, dll.): perubahan UX yang disengaja; navigasi via drawer.
- Item anchor dispensasi wakasek dihapus: sidebar tidak lagi deep-link ke section dispensasi halaman wakasek; halaman standalone `wakasek-dispensasi` menggantikannya (locked).
- localStorage `sijaga:nav:*` orphan: tidak berbahaya.

## Open Questions

None — semua keputusan sudah locked dari sesi sebelumnya.
