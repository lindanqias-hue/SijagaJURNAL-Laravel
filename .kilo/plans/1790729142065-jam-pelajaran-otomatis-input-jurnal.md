# Jam Pelajaran Otomatis pada Input Jurnal

## Tujuan

Hapus pemilih "Jam pelajaran" (checkbox jam ke-N) dari halaman Input Jurnal Guru.
Jam selalu dihitung server dari jadwal hari itu: jam yang sedang berjalan → jam berikutnya
→ lompat ke rentang berikutnya yang belum punya jurnal. Guru tidak bisa mengubah jam.

File utama: `resources/views/components/⚡input-jurnal.blade.php` (Livewire 4, SFC component).

## Keputusan yang sudah disepakati

1. Checkbox "Jam pelajaran" dihapus; jam selalu otomatis dari jadwal.
2. Kalau rentang jam otomatis sudah terisi jurnal → **otomatis lompat** ke rentang berikutnya
   yang belum terisi (bukan menampilkan error).
3. Kelas tetap bisa dipilih guru; jam dihitung otomatis **di dalam kelas yang dipilih**
   (berdasarkan waktu sekarang, lalu fallback ke jam berikutnya yang belum terisi).
4. Edit jurnal: jam tetap terkunci pada `editing->jam_ke` (perilaku sekarang, tidak diubah).

## Perubahan pada komponen PHP

### 1. Kunci property dari browser

Tambah atribut `use Livewire\Attributes\Locked;` dan beri atribut `#[Locked]` pada
`public array $jamTerpilih` (sekitar baris 66) dan `public $jam_ke` (baris 29).
Konfirmasi duluavailability `Livewire\Attributes\Locked` pada Livewire 4.4
(`vendor/livewire/livewire/src/Attributes/Locked.php`).

`id_kelas`, `tanggal`, `materi`, `absensi` tetap boleh diset dari browser.

### 2. Helper baru: `jadwalHariIni(?int $idKelas = null): Collection`

Pindahkan query jadwal di dalam `loadJadwal()` (baris 320-340) dan `getJamListProperty()`
(484-520) menjadi satu helper private:

- `id_guru` dari `session('id_pengguna')`
- `hari` dari `namaHariUntukTanggal()`
- filter `id_kelas` bila diberikan
- `whereExists` siswa pada kelas (pertahankan)
- `orderBy('jam_ke')`

### 3. Helper baru: `hitungJamOtomatis(?int $idKelas = null): ?Collection`

Returns `Collection` jadwal berurutan (rentang kontinu kelas yang sama) atau `null`
bila tidak ada jam tersisa. Algoritma:

1. Ambil `$jadwals = $this->jadwalHariIni($idKelas)`. Kosong → `null`.
2. Tentukan kandidat mulai dari `$jadwals`:
   - jadwal dengan `jam_mulai <= now <= jam_selesai` (jam sedang berlangsung), kalau ada;
   - kalau tidak, jadwal pertama dengan `jam_mulai > now`;
   - kalau tidak, `$jadwals->last()`.
3. `$rentang = $this->rentangJadwalBerurutan($jadwals, $kandidat)` (sudah ada, baris 1068).
4. Kalau semua `jam_ke` pada rentang **sudah punya jurnal** (guru + `id_kelas` rentang +
   `whereDate('tanggal', $this->tanggal)`, dengan `where('id_jurnal','!=',$editing->id_jurnal)`
   bila mode edit) → ulangi langkah 2-4 mulai dari indeks setelah rentang tadi
   (maksimum iterasi = jumlah jam hari itu, agar tidak loop).
5. Return rentang pertama yang masih kosong, atau `null`.

Query jurnal dipakai ulang lewat method private kecil `jamSudahTerisi(array $jamKe, int $idKelas): array`
(juga dipakai lagi di `save()`).

### 4. `terapkanJamOtomatis(?Collection $rentang)` (helper baru)

Set seluruh state dari server:

- `$this->jadwalAktif = $rentang->first()`
- `$this->id_kelas = $rentang->first()->id_kelas`
- `$this->jam_ke = (int) $rentang->first()->jam_ke`
- `$this->jamTerpilih = $rentang->pluck('jam_ke')->map(int)->all()`
- `$this->jamMulaiKe` / `$this->jamSelesaiKe`
- `$this->jamMulaiPembelajaran` / `$this->jamSelesaiPembelajaran`
- `$this->loadSiswa()` (tetap dipanggil dari pemanggilnya, bukan di dalam helper)

Bila `$rentang === null`: set `jadwalAktif = null`, `jamTerpilih = []`, `jam_ke = 1`,
keempat property jam `null`, `id_kelas = ''`, dan `loadSiswa()` — agar blade bisa
menampilkan kondisi "jam sudah habis".

### 5. `loadJadwal()` (baris 300-410) disederhanakan

- Validasi session + hari seperti sekarang.
- `$rentang = $this->hitungJamOtomatis()` (tanpa filter kelas → menentukan kelas aktif juga).
- `terapkanJamOtomatis($rentang)` + `loadSiswa()`.
- Blok "reset semua state" saat ini (356-370) pindah ke `terapkanJamOtomatis(null)`.

### 6. `updatedIdKelas()` (baris 695-726) disederhanakan

- Guard `modeIzin` tetap.
- `$this->jumlah_tidak_hadir = 0;`
- `$rentang = $this->hitungJamOtomatis((int) $this->id_kelas)`
- `terapkanJamOtomatis($rentang)` + `loadSiswa()`
- Hapus pemanggilan `sinkronkanJadwalTerpilih()` dan `$this->jamList`.

### 7. `save()` (baris 1311-2175)

- **Validasi (1340-1368)**: hapus rule `jamTerpilih.*` dan `jamTerpilih.required`.
  Ganti dengan pengecekan setelah nilai dihitung ulang; pesan memakai key baru `jadwal`
  (karena `jamTerpilih` tidak lagi dirender di blade).
- **Resolusi jam (1460-1571)**: nilai `$jamTerpilih` **tidak lagi diambil dari browser**.
  - Bila `$this->editing` → `$jamTerpilih = [(int) $this->editing->jam_ke]` (perilaku edit tetap).
  - Bila tidak → `$rentang = $this->hitungJamOtomatis((int) $this->jamAktif?->id_kelas)`;
    `null` → `addError('jadwal', 'Semua jam pelajaran pada tanggal ini sudah tercatat.')` + return.
  - Tetap pertahankan pengecekan `$jadwalTerpilih->count() !== count($jamTerpilih)`, cek berurutan,
    dan cek "semua jam berasal dari kelas yang sama" sebagai lapis kedua ( defense in depth).
- **Cek duplikat (1607-1653)**: tetap ada (untuk kasus race), pesan diubah ke key `jadwal`.
- **Catch QueryException (2089-2092)**: key error `jamTerpilih` → `jadwal`.
- **Setelah sukses (2144-2169)**: hapus cabang `$sisaJam`; selalu `$this->loadJadwal()`
  supaya jam otomatis berpindah ke rentang berikutnya yang kosong, dan reset
  `materi`/`catatan`.

### 8. Kode mati yang dihapus

- `toggleJamTerpilih()` (752-776)
- `updatedJamTerpilih()` (737-744)
- `updatedJamKe()` (890-903)
- `normalisasiJamTerpilih()` (784-842) — normalisasi sisi browser tidak relevan lagi
- `normalisasiJamTerpilihServer()` (860-882) — tidak ada input browser
- `getJamListProperty()` (484-520) — digantikan `jadwalHariIni()`
- `resetAbsensiTerkunci()` (905-913) hanya dipanggil oleh tiga hook di atas → ikut dihapus
  (hapus juga pemanggilannya di `updatedIdKelas` bila masih ada)
- `$this->jamTerpilihAsArray()` tetap dipakai di `save()`; pertahankan.

## Perubahan pada Blade

1. **Hapus blok checkbox** baris 2349-2367 (`<label>Jam pelajaran</label>` + grid
   checkbox + `form-text`). Kelas select (2340-2348) tetap, ubah `col-md-6` → tetap `col-md-6`.
2. **Alert jadwal (2323-2330)** menjadi satu-satunya sumber informasi jam:
   - sukses (`$jadwalAktif` ada, bukan mode izin): `alert alert-info`, teks
     "Jam pelajaran mengikuti jadwal: ke-N sampai ke-M (HH:MM–HH:MM). Jam tidak dapat diubah."
   - gagal (`@error('jadwal')` atau `$jadwalAktif` null setelah ada jam): `alert alert-warning`
     dengan pesan error.
   - kondisi "semua jam sudah terisi": tampilkan `alert alert-success` "Semua jam pelajaran
     hari ini sudah tercatat." dan tombol simpan otomatis disabled karena
     `count($siswa) === 0` (pastikan `loadSiswa()` benar-benar mengosongkan `$siswa`).
3. **Tombol simpan (2459)**: sudah `@disabled(!$modeIzin && !count($siswa))`; tambahkan
   syarat `|| !$jadwalAktif` agar tidak bisa menyimpan tanpa jam aktif.
4. **Modal Tinjau Jurnal (2470-2478)**: tambahkan baris "Jam: ke-N s/d M (HH:MM–HH:MM)"
   dan "Kelas" supaya review sesuai dengan yang tersimpan.
5. `@error('jamTerpilih')` di blade (2351) ikut terhapus bersama blok checkbox.

## Test yang harus diperbarui

`tests/Feature/InputJurnalMultiJamTest.php` — hapus pemanggilan `toggleJamTerpilih`:

- `test_satu_input_menyimpan_jurnal_untuk_semua_jam_berurutan_yang_dipilih`
  → ubah nama jadi `...jam_otomatis...`, hapus 2 baris `call('toggleJamTerpilih', ...)`
  dan `assertSet('jamTerpilih', ...)`. Tetap meyakinkan hasilnya `[1, 2, 3]` karena rentang kontinu
  jam 1-3 dipilih otomatis pada `travelTo 07:10`.
- `test_penyimpanan_dibatalkan_jika_salah_satu_jam_sudah_tercatat`
  → skenario berubah: setelah jurnal jam 1 tersimpan, `loadJadwal()` otomatis melompat ke
  rentang berikutnya (jam 2-3), jadi simpan kedua **berhasil** untuk jam 2-3, bukan error.
  Tulis ulang sebagai dua test:
  - `test_jam_otomatis_melompat_ke_rentang_berikutnya_yang_kosong`: assert jurnal tersimpan
    untuk jam 2 dan 3 setelah simpan pertama.
  - `test_jam_otomatis_mengosongkan_form_saat_semua_jam_terisi`: isi jam 1-3 satu per satu,
    lalu assert `jamTerpilih` = `[]`, `jadwalAktif` null, dan `call('save')`
    menambah error pada key `jadwal`.

`tests/Feature/DispensasiJurnalSyncTest.php` baris 78: hapus `->set('jam_ke', 1)`
(komponen sudah otomatis memilih jam ke-1 dari jadwal tunggal pada test tersebut).

Tambahkan test baru bila perlu di file yang sama:

- `test_jam_otomatis_dihitung_ulang_saat_guru_memilih_kelas_lain` — dua kelas dengan jam
  berbeda, pilih kelas kedua, assert `jam_ke` mengikuti kelas kedua.

## Validasi

```
php artisan test --compact --filter=InputJurnalMultiJamTest
php artisan test --compact --filter=DispensasiJurnalSyncTest
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

## Risiko

- Guru tidak lagi bisa mengisi jam yang terlewat secara bebas; untuk jadwal hari ini rentang
  kosong selalu dipilih mulai dari jam berjalan ke depan, sehingga jam kosong yang lebih awal
  hanya terisi bila jam yang lebih awal belum terisi (masihreachable karena auto-skip memilih
  rentang kosong pertama yang >= jam berjalan). Ini perilaku yang diminta.
- `#[Locked]` membuat `jam_ke`/`jamTerpilih` tidak bisa di-set dari client; pastikan tidak ada
  template lain yang melakukan `wire:model` pada kedua property itu (hanya `⚡input-jurnal`
  yang memakainya — sudah diverifikasi lewat grep).
