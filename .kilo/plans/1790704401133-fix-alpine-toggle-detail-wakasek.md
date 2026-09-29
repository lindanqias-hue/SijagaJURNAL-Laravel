# Fix Toggle Detail Wakasek (Monitoring Jurnal & Rekap Per Kelas)

## Masalah

Tombol `▶ Detail` tidak bereaksi di dua tempat:

- Monitoring Jurnal (`⚡wakasek.blade.php:702`)
- Rekap Per Kelas (`⚡wakasek.blade.php:802`)

Keduanya terkonfirmasi sebagai satu akar masalah yang sama, **bukan** dua bug terpisah.

## Akar masalah (sudah diverifikasi terhadap source)

`⚡wakasek.blade.php:615` — `x-init` pada elemen root `x-data`:

```js
x-init="syncSection(); window.addEventListener('hashchange', () => syncSection()); this.$watch('openDetails', () => {}); this.$on('toggle-detail', e => { ... this.openDetails ... });"
```

Magics Alpine (`$on`, `$watch`, `$dispatch`) **tidak** tersedia lewat `this`. Bukti dari bundle
`vendor/livewire/livewire/dist/livewire.js`:

- `injectMagics(overriddenMagics, el)` (line 1504) menaruh magics di object terpisah yang masuk
  ke `dataStack`, lalu dipakai sebagai `with (scope)`. Jadi magics hanya bisa dipanggil sebagai
  **identifier bare** (`$on(...)`), bukan properti `this.$on`.
- `directive("init", ...)` memanggil `evaluate22(expression, {}, false)` (line 4683–4687) tanpa
  `extras.context`, sehingga di `generateEvaluatorFromString` (line 1556) `this` =
  `func.call(undefined, ...)` = **`window`**.

Konsekuensi: `this.$watch` melempar `TypeError: this.$watch is not a function` saat Alpine
init. Karena posisinya **sebelum** `this.$on('toggle-detail', ...)`, eksekusi berhenti di situ dan
listener `toggle-detail` **tidak pernah terdaftar**. Tombol tetap mengirim
`$dispatch('toggle-detail', ...)` ke event yang tidak ada listenernya → tidak terjadi apa-apa.
`syncSection()` dan navigasi section tetap jalan karena keduanya memakai identifier bare.

Pendukung: `this.$watch`/`this.$on` hanya muncul di file ini (1 dari 1 occurrence di seluruh
`resources/views`), sedangkan interaksi Alpine yang berhasil di project ini semua memakai
identifier bare dan `x-on:click`. Jadi handler Rekap Per Kelas memang belum pernah berfungsi.

## Keputusan

- Logika toggle dipindah ke method `toggleDetail(index)` di dalam object `x-data`.
- Tombol memanggil method secara langsung: `x-on:click="toggleDetail(...)"`.
- `$on`, `$watch`, dan `$dispatch` dihapus dari `x-init` dan dari kedua tombol.
- Gaya atribut diseragamkan ke `x-on:click` (sudah dipakai di semua komponen lain; `@click`
  sebenarnya valid juga karena Livewire memetakan `@` → `x-on:`, tapi tidak dipakai di
  tempat lain di project ini).

## Perubahan

### 1. `resources/views/components/⚡wakasek.blade.php:601-615` — root `x-data` / `x-init`

Tambahkan method baru di dalam object `x-data`, setelah `openSection(section)`. `this` di
dalam method `x-data` merujuk ke data proxy (Alpine memanggil method via
`value.apply(scope, params)`), jadi `this.openDetails` aman di sini — berbeda dari `x-init`.

```js
    openSection(section) {
        this.activeSection = section;
        window.location.hash = section;
    },
    toggleDetail(index) {
        if (this.openDetails.includes(index)) {
            this.openDetails.splice(this.openDetails.indexOf(index), 1);
        } else {
            this.openDetails.push(index);
        }
    }
}" x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">
```

`this.$watch('openDetails', () => {})` dihapus — watcher kosong tanpa efek (`push`/`splice` pada
array reaktif sudah memicu `x-show`). `this.$on(...)` dihapus karena sudah digantikan pemanggilan
langsung. `window.addEventListener('hashchange', () => syncSection())` **tetap** — arrow
function di dalam blok `with (scope)` masih menutupi scope Alpine, jadi `syncSection()` resolves
dengan benar.

### 2. Baris 702 — tombol detail Monitoring Jurnal

`@click` → `x-on:click`, tanpa dispatch:

```html
<button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="toggleDetail('{{ $detailKey }}')">
```

Dua `<span x-show="!openDetails.includes(...)">` / `openDetails.includes(...)` di dalam tombol
tetap apa adanya. `wire:key`, `data-search`, dan badge status tidak berubah.

### 3. Baris 703 — baris detail guru (Monitoring Jurnal)

Dua perbaikan tambahan pada baris detail:

- **Hilangkan `x-transition`.** Baris ini `<tr>`; transisi opacity/transform pada elemen tabel
  tidak menambah nilai dan berisiko menetralkan `display` yang dikelola `x-show`.
- **Ikuti filter pencarian.** Saat baris kelas tersaring oleh `searchJurnal`, baris detail yang
  sebelumnya sudah dibuka akan tetap terlihat tanpa baris induknya (baris yatim). Gabungkan
  kondisi:

```html
<tr wire:key="monitoring-kelas-detail-{{ $item['id_kelas'] }}" data-search="{{ $kunciPencarian }}" x-show="openDetails.includes('{{ $detailKey }}') && (!searchJurnal || $el.dataset.search.includes(searchJurnal))">
```

`$kunciPencarian` sudah dihitung di baris sebelumnya dan memuat nama kelas + semua nama guru +
mapel, sehingga baris detail dan baris kelas disaring dengan kunci yang sama.

### 4. Baris 802-804 — tombol detail Rekap Per Kelas

```html
<button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="toggleDetail({{ $index }})">
```

`$index` tetap integer 0..N dan `openDetails` di baris 803-804 serta panel detail baris 814
(`x-show="openDetails.includes({{ $index }})" x-transition ...` pada `<div>`) tidak berubah.
Tidak ada bentrok dengan key string `kelas-<id_kelas>` milik Monitoring Jurnal.

### 5. `tests/Feature/WakasekMonitoringJurnalPerKelasTest.php` — guard regresi

Tambah satu test yang mengunci markup Alpine (perilaku Alpine tidak bisa diuji lewat PHPUnit):

```php
public function test_tombol_detail_terhubung_ke_method_toggle(): void
```

- Setup: 1 kelas + 1 guru + 1 jadwal hari ini (helper yang sudah ada).
- `Livewire::test('wakasek')`
  - `->assertSeeHtml('toggleDetail(index)')` — method ada di `x-data`.
  - `->assertSeeHtml("x-on:click=\"toggleDetail('kelas-{$idKelas}')\"")` — tombol kelas terhubung.
  - `->assertDontSee('this.$on', false)` dan `->assertDontSee('this.$watch', false)` —
      regression guard supaya pola `this.<magic>` tidak kembali.

Pakai flag `false` pada `assertDontSee` supaya tidak ada HTML-escaping.

## Tidak diubah

- `getMonitoringJurnalPerKelasProperty()`, `getMonitoringGuruProperty()`, `getRekapPerKelasProperty()`.
- Signature/konten property, badge, `wire:key`, `data-search` baris kelas.
- Route, middleware, model, migration, section lain.

## Verifikasi

1. `php artisan test --compact --filter=WakasekMonitoringJurnalPerKelas` → semua hijau.
2. `php artisan test --compact` → suite penuh tetap hijau.
3. `vendor/bin/pint --dirty --format agent`.
4. **Manual di browser (wajib — project ini tidak punya Dusk/Playwright, dan perilaku Alpine
   tidak bisa dibuktikan dari PHPUnit):**
   - `/wakasek` → Monitoring Jurnal → klik `▶ Detail` pada satu baris kelas: baris detail guru
     harus muncul, tombol berubah jadi `▼ Tutup`, dan klik lagi menutupnya.
   - Buka kelas A, lalu kelas B: keduanya harus bisa terbuka bersamaan (independen).
   - `/wakasek` → Rekap → Rekap Per Kelas → `▶ Detail` harus membuka panel detail.
   - Monitoring Jurnal: buka detail, lalu ketik di kotak pencarian → baris detail ikut
     tersaring; kosongkan pencarian → baris detail kembali.
   - Cek console browser: tidak boleh ada `Alpine Expression Error`.
## Risiko

- **Perilaku berubah di luar yang dilaporkan:** navigasi section (`openSection`/`syncSection`)
  juga menyentuh `x-init` yang sama. Karena `syncSection()` sudah berjalan normal sebelumnya dan
  hanya bagian `this.$watch`/`this.$on` yang dihapus, tidak ada regresi yang diharapkan — tetapi
  verifikasi manual harus mencakup pindah section lewat tombol dashboard dan lewat URL hash
  (`/wakasek#rekap`).
- **Assertion `assertSeeHtml` bersifat struktural:** test ini hanya menangkap regresi markup,
  bukan regresi perilaku Alpine. Verifikasi behaviour tetap relies pada cek manual di browser.
- **Orphan `hashchange` listener:** sudah ada sebelumnya dan tidak terkait isu yang dilaporkan,
  sehingga di luar scope.
