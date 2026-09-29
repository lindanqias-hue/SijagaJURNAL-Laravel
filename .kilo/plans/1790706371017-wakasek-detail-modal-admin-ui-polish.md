# Modal Detail Wakasek + Polish UI/UX Admin & Wakasek

## Tujuan

1. Tombol `▶ Detail` (Monitoring Jurnal + Rekap Per Kelas) membuka **modal di tengah layar** berisi
   rekap jurnal, ditutup lewat tombol **"Tutup Detail"** — bukan lagi baris tabel yang mengembang
   inline.
2. Rapikan & perbaiki responsivitas **Admin** dan **Wakasek** (Paket A: fokus mobile), memakai satu
   komponen modal bersama dan satu set kelas CSS baru di `public/assets/css/style.css`.

## Keputusan (sudah dikonfirmasi user)

| Keputusan | Nilai |
|---|---|
| Bentuk detail | Modal di tengah layar |
| Komponen modal | Satu komponen Blade bersama (`<x-app-modal>`), dipakai ulang di 4 file |
| Cakupan polish | Admin + Wakasek saja |
| Kedalaman | Paket A — fokus mobile (bukan paket B/C) |

## Temuan pembanding (sudah diverifikasi di kode)

- `.tab-pill-group` / `.tab-pill` **sudah ada** di `style.css:469-471` tapi **tidak dipakai di file
  mana pun** — siap dipakai untuk tab-bar navigasi section.
- `.rekap-back` (`⚡wakasek.blade.php:769`), `.role-page-actions`, `.wakasek-content-card`,
  `.wakasek-page-header`, `.wakasek-summary-mark`, `.rekap-card`, `.rekap-toolbar` **tidak punya
  aturan CSS sama sekali** di `style.css` maupun `<style>` inline. Tombol "← Kembali ke semua rekap"
  sekarang tampil sebagai tombol browser mentah. Inilah salah satu sumber tampilan "berantakan".
- `.admin-back-link` (`⚡admin.blade.php:413-427`) adalah **dead CSS** — markup memakai
  `btn btn-outline-primary btn-sm`. slated dihapus.
- 8 section admin mengulang blok identik "← Kembali ke Dashboard" + input cari
  (`⚡admin.blade.php:470-474, 484, 492, 500, 508, 516, 524, 532`).
- `<div class="modal">` tidak boleh berada di dalam `<tbody>/<tr>` (HTML invalid) → isi detail wajib
  keluar dari tabel dan pindah ke modal yang dimiliki Livewire.
- Loader Livewire (`vendor/livewire/livewire/src/Finder/Finder.php:198-210`) mengenali prefiks `⚡`
  untuk SFC. File **tanpa** `new class` (Blade component biasa) tidak akan ternodet sebagai SFC,
  jadi `app-modal.blade.php` aman menjadi `<x-app-modal>` biasa.

## Perubahan

### 1. `resources/views/components/app-modal.blade.php` (BARU)

Blade component anonymous (bukan SFC — jangan ada `new class`).

```blade
@props([
    'id' => 'app-modal',
    'title',
    'subtitle' => null,
    'close',            // nama method Livewire, mis. 'closeModal' / 'tutupDetailJurnal'
    'closeParams' => '', // mis. '' atau "1, '2026-09-28'"
    'size' => 'md',      // sm | md | lg
])
```

Struktur:

- Overlay `position-fixed top-0 start-0 w-100 h-100` + `d-flex align-items-center justify-content-center p-3`,
  `style="z-index:1060;background:rgba(15,23,42,.62);backdrop-filter:blur(3px)"`,
  `wire:click.self="{{ $close }}({{ $closeParams }})"`,
  `wire:keydown.escape.window="{{ $close }}({{ $closeParams }})"`.
- Shell `<section id="{{ $id }}" class="card-custom app-modal" role="dialog" aria-modal="true"
  aria-labelledby="{{ $id }}-title" tabindex="-1" x-init="$nextTick(() => $el.focus())">`
  dengan `app-modal--{size}` untuk lebar (`sm` 480px, `md` 720px, `lg` 1080px).
- Header `.app-modal__head`: `<h3 id="{{ $id }}-title">` + `<div class="text-muted small">{{ $subtitle }}</div>` +
  `<button type="button" class="btn-close" aria-label="Tutup" wire:click="{{ $close }}({{ $closeParams }})">`.
- Body `<div class="app-modal__body">{{ $slot }}</div>` — `overflow-y:auto; max-height:calc(90vh - 160px)`.
- Slot opsional `footer` → `<div class="app-modal__foot">{{ $slot->footer }}</div>` (border-top,
  justify-content flex-end, `flex-wrap:wrap`).
- Fokus: `x-init` memfokuskan dialog saat node baru ditambahkan Livewire. **Focus trap penuh di luar
  scope** (paket B) — cukup fokus awal + tombol tutup + Esc + klik backdrop.

### 2. `public/assets/css/style.css` — kelas baru (tambahkan, jangan ubah yang ada)

```css
/* Modal */
.app-modal { width: 100%; max-height: 90vh; display: flex; flex-direction: column;
  box-shadow: 0 24px 80px rgba(15,23,42,.28); border: 1px solid #dbeafe; }
.app-modal--sm { max-width: 480px; } .app-modal--md { max-width: 720px; } .app-modal--lg { max-width: 1080px; }
.app-modal__head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
  padding:16px 20px; border-bottom:1px solid var(--border); background:linear-gradient(90deg,#f4f8ff,#fff); }
.app-modal__head h3 { margin:0; font-size:1.05rem; font-weight:800; color:#183153; }
.app-modal__body { padding:16px 20px; overflow-y:auto; }
.app-modal__foot { padding:14px 20px; border-top:1px solid var(--border); background:#f8fafc;
  display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
@media (max-width: 575.98px) { .app-modal__head,.app-modal__body,.app-modal__foot { padding-left:14px; padding-right:14px; } }

/* Navigasi section sticky */
.section-tabs { position: sticky; top: 0; z-index: 1020; margin-bottom: 16px;
  overflow-x: auto; scrollbar-width: none; }
.section-tabs::-webkit-scrollbar { display: none; }
.section-tabs .tab-pill-group { flex-wrap: nowrap; width: max-content; min-width: 100%; }
@media (max-width: 991.98px) { .section-tabs { top: 66px; } } /* tinggi .topbar-mobile */

/* Tombol kembali (menggantikan .rekap-back) */
.btn-back { display:inline-flex; align-items:center; gap:6px; min-height:38px; padding:6px 12px;
  border-radius:9px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8;
  font-size:12.5px; font-weight:600; }
.btn-back:hover { background:#dbeafe; border-color:#93c5fd; color:#1e40af; }

/* Tabel -> kartu di layar kecil */
@media (max-width: 767.98px) {
  .table-stack { display: block; }
  .table-stack thead { display: none; }
  .table-stack tbody { display: block; }
  .table-stack tr { display: block; border: 1px solid var(--border); border-radius: 12px;
    background: #fff; padding: 6px 2px; margin: 0 0 10px; }
  .table-stack td { display: flex; justify-content: space-between; align-items: flex-start;
    gap: 14px; border: 0; padding: 8px 12px; text-align: right; }
  .table-stack td::before { content: attr(data-label); flex: 0 0 auto; text-align: left;
    font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: var(--muted); }
  .table-stack td[data-label=""]::before,
  .table-stack td.table-stack__full::before { display: none; }
  .table-stack td.table-stack__full { display: block; text-align: left; }
}
```

`resources/css/app.css` (Tailwind) **tidak disentuh** — tidak ada build frontend yang dipakai halaman ini
(Alpine datang dari bundle Livewire, `resources/js/app.js` kosong).

### 3. `resources/views/components/⚡wakasek.blade.php`

**3a. State Livewire baru** (taruh di blok properti, dekat `rekapTerbuka`):

```php
public ?int $detailKelasId = null;
public ?string $detailRekapKunci = null;

public function bukaDetailJurnal(int $idKelas): void
{
    abort_unless($this->monitoringJurnalPerKelas->contains('id_kelas', $idKelas), 404);
    $this->detailKelasId = $idKelas;
}

public function tutupDetailJurnal(): void
{
    $this->detailKelasId = null;
}

public function getDetailJurnalProperty(): ?array
{
    return $this->detailKelasId === null
        ? null
        : $this->monitoringJurnalPerKelas->firstWhere('id_kelas', $this->detailKelasId);
}

public function bukaDetailRekap(string $kunci): void
{
    abort_unless($this->rekapPerKelas->contains('kunci', $kunci), 404);
    $this->detailRekapKunci = $kunci;
}

public function tutupDetailRekap(): void
{
    $this->detailRekapKunci = null;
}

public function getDetailRekapProperty(): ?array
{
    return $this->detailRekapKunci === null
        ? null
        : $this->rekapPerKelas->firstWhere('kunci', $this->detailRekapKunci);
}
```

Tambahkan `'kunci' => $idKelas.'-'.$tanggalStr` pada item di `getRekapPerKelasProperty()`
(sekitar `⚡wakasek.blade.php:440-451`). `abort_unless` mencegah `wire:click` crafted membuka key asing.

**3b. Root `x-data`** (`⚡wakasek.blade.php:601-620`): hapus `openDetails: []` dan method
`toggleDetail(index)`. `syncSection()` / `openSection()` / `x-init` tetap.

**3c. Tab-bar navigasi** — sisipkan tepat setelah blok flash message (sekitar line 644), sebelum
`<section id="wakasek-dashboard">`:

```blade
<nav class="section-tabs" aria-label="Navigasi section wakasek">
    <div class="tab-pill-group">
        @foreach ([
            'dashboard' => 'Dashboard',
            'monitoring-guru' => 'Monitoring Guru',
            'monitoring-jurnal' => 'Monitoring Jurnal',
            'dispensasi' => 'Dispensasi',
            'pengajuan-izin' => 'Pengajuan Izin',
            'rekap' => 'Rekap',
        ] as $kunciSection => $labelSection)
        <button type="button" class="tab-pill" :class="{ 'active': activeSection === '{{ $kunciSection }}' }"
            :aria-current="activeSection === '{{ $kunciSection }}' ? 'page' : false"
            x-on:click="openSection('{{ $kunciSection }}')">{{ $labelSection }}</button>
        @endforeach
    </div>
</nav>
```

**3d. Monitoring Jurnal** (`⚡wakasek.blade.php:700-717`):

- Hapus blok `role-page-actions` "← Kembali ke Dashboard" (line 702) — digantikan tab-bar.
- Tombol kolom Aksi (line 709) →
  `<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="bukaDetailJurnal({{ $item['id_kelas'] }})">&#9654; Detail</button>`
  (hapus kedua `<span x-show>` dan `@php($detailKey = ...)`).
- **Hapus seluruh `<tr>` detail guru** (line 710-714) beserta `@php($detailKey = ...)` (line 707).
  `$kunciPencarian` (line 708) tetap dipakai untuk `data-search` baris kelas.
- `<table>` dapat class `table-stack`; setiap `<td>` dapat `data-label` (`Kelas`, `Jam Terisi`,
  `Status`, `Aksi`). `<td colspan="4">` pada baris `@empty` dapat `data-label=""`.
- Tambah modal **setelah** penutup `</section>` monitoring-jurnal:

```blade
@if ($this->detailJurnal)
<x-app-modal id="modal-detail-jurnal" title="Detail Jurnal — {{ $this->detailJurnal['nama_kelas'] }}"
    :subtitle="count($this->detailJurnal['guru']).' guru · '.$this->detailJurnal['terisi'].'/'.$this->detailJurnal['total_jam'].' jam terisi'"
    close="tutupDetailJurnal" size="lg">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 table-stack">
            <thead><tr><th>Guru</th><th>Mapel</th><th>Jam</th><th>Status Jurnal</th><th>Validasi</th></tr></thead>
            <tbody>
            @forelse ($this->detailJurnal['guru'] as $guru)
            <tr>
                <td data-label="Guru">{{ $guru['nama'] }}</td>
                <td data-label="Mapel">{{ $guru['mapel'] }}</td>
                <td data-label="Jam">{{ collect($guru['jam'])->map(fn (int $jam): string => 'Ke-'.$jam)->join(', ') }}</td>
                <td data-label="Status Jurnal"><span class="badge {{ $guru['status_jurnal'] === 'Terisi Semua' ? 'bg-success' : ($guru['status_jurnal'] === 'Sebagian' ? 'bg-info' : 'bg-warning text-dark') }}">{{ $guru['status_jurnal'] === 'Terisi Semua' ? 'Sudah Mengisi' : $guru['status_jurnal'] }}</span></td>
                <td data-label="Validasi"><span class="badge {{ $guru['status_validasi'] === 'Divalidasi' ? 'bg-success' : ($guru['status_validasi'] === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $guru['status_validasi'] ?? '-' }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5" data-label="" class="text-center text-muted py-4">Belum ada jadwal guru untuk kelas ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-slot:footer>
        <button type="button" class="btn btn-outline-secondary" wire:click="tutupDetailJurnal">Tutup Detail</button>
    </x-slot:footer>
</x-app-modal>
@endif
```

**3e. Section lain wakasek** — hapus blok `role-page-actions` (line 721, 735, 748), ubah
`.rekap-back` (line 769) menjadi `class="btn-back"`, tambahkan `table-stack` + `data-label` pada
tabel monitoring-guru (line 738-742) dan dispensasi (line 751-755).

**3f. Rekap Per Kelas** (`⚡wakasek.blade.php:802-870`):

- Tombol (line 809-812) → `wire:click="bukaDetailRekap('{{ $item['kunci'] }}')"` + label tetap
  `&#9654; Detail` (tanpa `<span x-show>`).
- **Hapus** `<div x-show="openDetails.includes(...)" x-transition ...>` beserta tabel periodenya
  (line 821-870).
- Tambah modal `modal-detail-rekap` setelah penutup section rekap, mengulang isi tabel
  Jam/Waktu/Mapel-Guru/Status/Validasi (line 822-870) dengan `data-label`, judul
  `"Rekap {{ $this->detailRekap['kelas'] }} — {{ $this->detailRekap['tanggal']->format('d/m/Y') }}"`,
  subtitle `{{ $this->detailRekap['hari'] }} · {{ $this->detailRekap['terisi'] }}/{{ $this->detailRekap['total_jam'] }} jam terisi`,
  `close="tutupDetailRekap"`, footer tombol **"Tutup Detail"**.

### 4. `resources/views/components/⚡admin.blade.php`

- **Hapus `<style>` block** (line 410-428) beserta `.admin-back-link` yang dead.
- **Sisipkan tab-bar** setelah blok flash message (setelah line 449), sebelum `<section id="admin">`,
  dengan 9 tab: `admin` Dashboard, `pengguna` Pengguna, `guru` Guru, `siswa` Siswa, `kelas` Kelas,
  `jadwal` Jadwal, `jadwal-piket` Piket, `jurnal` Jurnal, `dispensasi` Dispensasi.
  Untuk tab `pengguna` tambahkan `x-on:click="$wire.set('filterRole', '')"` sebelum `openSection()`
  supaya tidak mewarisi filter dari stat-card Guru.
- **Hapus 8 blok** `role-page-actions`/back-link (line 470, 484, 492, 500, 508, 516, 524, 532).
- **Ganti modal CRUD** (line 538-580) dengan `<x-app-modal>`:
  `id="modal-admin-form"`, `:title="($editingId ? 'Edit' : 'Tambah').' '.match($modalType){...}"`,
  `close="closeModal"`, `size="md"`, body = `<form wire:submit="save" class="app-modal__form">`
  (isi `row g-3` yang sekarang, plus **error summary** di atasnya), footer = Batal + Simpan
  (pindahkan `<div class="d-flex justify-content-end gap-2 mt-4">` line 576 ke slot footer).
  Tambahkan `<div class="alert alert-danger py-2" role="alert" x-show="...">`? **Tidak** — cukup
  error summary Blade:
  `@php($errorsModal = $errors->hasAny(['form.nama','form.nip','form.role','form.mapel_diampu','form.status_kepegawaian','form.no_hp','form.id_kelas','form.password','form.nama_siswa','form.jam_ke','form.tanggal','form.jam_mulai','form.jam_selesai','form.status','form.keterangan']))`
  → jika true, render `<div class="alert alert-danger">` berisi `<ul>` semua pesan.
- **Aksi tabel** (line 477, 494, 502, 510, 518): ganti `btn btn-sm btn-outline-primary/danger` +
  `wire:confirm` dengan kelas `btn-edit` / `btn-hapus` yang **sudah ada** di `style.css:435-438`
  (lebih ringkas, tidak memaksa `min-height:44px` traumatized). `wire:confirm` **tetap dipakai**
  (paket konfirmasi kustom = paket C, di luar scope).
- **`table-stack` + `data-label`** pada 8 tabel (line 475, 485, 493, 501, 509, 517, 525, 533).
  `data-label` mengikuti judul kolom; `<td>` Aksi dan baris `@empty` (`colspan`) memakai
  `data-label="Aksi"` / `data-label=""`.
- **Perbaiki bug page** di `updatingFilterRole()` (sekitar line 96-99): tambahkan
  `$this->resetPage('guruPage');` supaya stat-card "Guru" tidak membuka `guruPage` yang di luar range.
- **Stat-card** (line 457-464): hapus `style="cursor:pointer;"` (8x) — `button` sudah punya cursor;
  `cursor:pointer` pindah ke `.stat-card` di `style.css`. Tambahkan `aria-label` pada tiap stat-card.
- **Banner dashboard** (line 452-455): ganti `welcome-banner` + 2 inline style dengan
  `role-page-header` yang sudah ada (konsisten dengan halaman role lain), hapus inline style.

### 5. `tests/Feature/WakasekMonitoringJurnalPerKelasTest.php`

- **Tulis ulang** `test_tombol_detail_terhubung_ke_method_toggle` (sekarang menguji `toggleDetail(index)` +
  `x-on:click="toggleDetail('kelas-X')"`, keduanya tidak berlaku lagi). Ganti menjadi:

```php
public function test_detail_jurnal_dibuka_lewat_modal(): void
{
    // setup 1 kelas + 1 guru + 1 jadwal (helper existing)
    $component = Livewire::test('wakasek')
        ->assertDontSeeHtml('wire:click="bukaDetailJurnal({{ $idKelas }})"') // sanity: belum terbuka
        ->call('bukaDetailJurnal', $idKelas)
        ->assertSee('Tutup Detail')
        ->assertSee('Andi Saputra')
        ->assertSeeHtml('id="modal-detail-jurnal"')
        ->call('tutupDetailJurnal')
        ->assertDontSee('Tutup Detail');
}
```

  plus `test_detail_rekap_dibuka_lewat_modal()` yang memanggil `bukaDetailRekap($kunci)` setelah
  `getRekapPerKelasProperty` tersedia, dan `test_markup_tidak_memakai_magic_alpine_$this()` yang
  mempertahankan guard `assertDontSee('this.$on', false)` / `assertDontSee('this.$watch', false)`.

- Ganti `assertSeeHtml('wire:click="bukaDetailJurnal(...)"')` dengan `assertSeeHtml` pada string
  tanpa escaping; pakai flag `false` di `assertDontSee` agar tidak di-escape.

## Tidak diubah

- Route, middleware, model, migration, seeder.
- `getMonitoringJurnalPerKelasProperty()`, `getMonitoringGuruProperty()`, `getRekapPerKelasProperty()`
  (kecuali penambahan key `kunci`), `exportCsv()`, `getDataCetakProperty()`.
- Aturan CSS lama di `style.css` (kecuali penambahan `cursor:pointer` pada `.stat-card`).
- `wire:confirm` masih dipakai (paket C di luar scope).
- `⚡input-jurnal.blade.php` & `⚡guru-piket.blade.php` **tidak disentuh** pada sesi ini; keduanya
  sudah punya modal sendiri yang berfungsi. Keduanya bisa dimigrasikan ke `<x-app-modal>` di sesi
  berikutnya tanpa mengubah perilakunya.
- Layout `layouts/app.blade.php` tidak diubah.

## Verifikasi

1. `php artisan test --compact --filter=WakasekMonitoringJurnalPerKelas` → hijau.
2. `php artisan test --compact` → suite penuh hijau (baseline saat ini: 33 test).
3. `vendor/bin/pint --dirty --format agent`.
4. **Manual di browser (wajib — tidak ada Dusk/Playwright di project ini):**
   - `/wakasek` → Monitoring Jurnal → `▶ Detail` → modal muncul berisi tabel guru, tombol
     **Tutup Detail** menutupnya, `X`/Esc/klik backdrop juga menutup. Cek console: tidak ada
     `Alpine Expression Error`.
   - `/wakasek` → Rekap → Rekap Per Kelas → `▶ Detail` → modal rekap jam per kelas terbuka.
   - Klik detail kelas A lalu tutup, lalu kelas B — tidak ada state tertinggal.
   - **375px (mobile)**: tabel admin & wakasek berubah jadi kartu bertumpuk dengan label kolom;
     tab-bar navigasi bisa di-scroll horizontal dan menempel di bawah topbar; modal occupying
     tinggi layar dengan body scrollable.
   - **1440px (desktop)**: tabel tetap tabel, tab-bar tidak menutupi konten saat scroll.
   - `/admin` semua 9 section: tab-bar menyorot section aktif, tombol Edit/Hapus tetap bekerja,
     `wire:confirm` tetap muncul, form modal validasi menampilkan error summary.
   - Navigasi lewat sidebar anchor (`#pengguna`, `#jadwal-piket`) masih membuka section yang benar.

## Risiko

- **Sticky tab-bar bisa tidak sticky** bila ada ancestor `overflow:hidden`. `.main-content` dan root
  `x-data` tidak punya `overflow`, tapi harus diverifikasi visual di browser. Fallback: hapus
  `position:sticky` dari `.section-tabs` (fungsionalitas tetap utuh).
- **Offset 66px** untuk topbar mobile adalah estimasi (padding 14px×2 + tombol 38px). Verifikasi
  di 375px; sesuaikan angkanya bila ada celah/ganda.
- **`.table-stack` mengubah `<table>` jadi `display:block`** — pastikan tidak ada `<tr>`/`<td>` yang
  bergantung pada `display: table-cell` (mis. `colspan` pada baris kosong sudah ditangani dengan
  `data-label=""`).
- **Pergantian dari Alpine ke Livewire menambah 1 round-trip** saat membuka detail. Dampaknya kecil
  (data sudah dihitung server), tapi `wire:loading` opacity belum termasuk paket A.
- **Regresi test lama**: `test_tombol_detail_terhubung_ke_method_toggle` masih menguji pola Alpine
  yang dihapus — wajib ditulis ulang di langkah 5, kalau tidak suite akan gagal.
- Assertion `assertSee`/`assertSeeHtml` bersifat struktural; perilaku Alpine (buka/tutup modal)
  tetap harus diverifikasi manual di browser.
