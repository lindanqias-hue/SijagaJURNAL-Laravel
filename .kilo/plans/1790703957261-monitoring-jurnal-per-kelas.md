# Monitoring Jurnal Wakasek Dibuat Per Kelas

## Tujuan

Di section **Monitoring Jurnal** (`resources/views/components/⚡wakasek.blade.php`), tabel saat ini
1 baris per (guru + kelas + jam). Diubah jadi **1 baris per kelas**, dan di dalam baris kelas
terdapat daftar **guru mana yang sudah mengisi jurnal dan mana yang belum** (expand saat diklik).

Hanya tampilan. Tidak ada perubahan skema database, model, alur input jurnal guru, maupun
section lain (Monitoring Guru, Dispensasi, Pengajuan Izin, Rekap, angka dashboard).

## Keputusan yang sudah dikunci

| Keputusan | Nilai |
| --- | --- |
| Cakupan | Hanya tabel di section `monitoring-jurnal` |
| Bentuk | Ganti tabel per guru dengan tabel per kelas (bukan toggle) |
| Detail | Expand per baris kelas, di dalam tampil **per guru** (bukan per jam) |
| Kelas tanpa jadwal hari ini | Tidak ditampilkan |
| Data | tetap hari ini, tanpa filter tanggal baru |

## Konteks kode

- `getMonitoringGuruProperty()` (`⚡wakasek.blade.php:132`) sudah mengambil jadwal hari ini yang di-`leftJoin`
  jurnal, lalu menambah atribut `id_jurnal`, `status_jurnal`, `status_konfirmasi`, `status_kehadiran`.
  Property ini **tetap dipakai** oleh `getStatsProperty()`, `getJadwalMengajarSekarangProperty()`,
  `getGuruTanpaKeteranganProperty()`, dan section Monitoring Guru. Jangan dihapus.
- Pola expand + Alpine sudah ada di section Rekap (`openDetails` + `$dispatch('toggle-detail', ...)`)
  di `x-data` pada `⚡wakasek.blade.php:540-549`. Pattern itu dipakai ulang.
- Pencarian memakai `x-show` + `data-search` (bukan query server), lihat baris `631` dan `634`.

## Rencana Implementasi

### 1. Tambah computed property baru

Di `⚡wakasek.blade.php`, taruh tepat setelah `getMonitoringGuruProperty()`, nama
`getMonitoringJurnalPerKelasProperty(): \Illuminate\Support\Collection`.

Turunkan dari `$this->monitoringGuru` (hanya baris yang punya `id_kelas`), lalu:

1. `->groupBy('id_kelas')`
2. Per grup, `->groupBy('id_guru')` untuk detail guru.
3. Urutkan kelas dengan `sortBy` pada `nama_kelas` memakai `SORT_NATURAL | SORT_FLAG_CASE`
   (konsisten dengan sorting nama guru di tabel lama).
4. Guru di dalam kelas diurutkan dengan `sortBy('nama_guru', ...)`.
5. Jam di dalam guru diurutkan numerik.

Bentuk setiap item kelas (array, gaya sama seperti `getRekapPerKelasProperty()`):

```php
[
    'id_kelas' => int,
    'nama_kelas' => string,
    'total_jam' => int,          // jumlah baris jadwal kelas itu hari ini
    'terisi' => int,             // baris yang punya id_jurnal
    'kosong' => int,             // total_jam - terisi
    'status_kelas' => 'Lengkap'|'Sebagian'|'Kosong',  // kosong==0 / terisi>0 / terisi==0
    'guru' => [
        [
            'id_guru' => string,
            'nama' => string,      // dari nama_guru
            'mapel' => string,     // mapel_diampu, default '-'
            'jam' => [int, ...],   // jam_ke milik guru tsb di kelas tsb
            'jam_terisi' => [int, ...],
            'jam_kosong' => [int, ...],
            'status_jurnal' => 'Terisi Semua'|'Sebagian'|'Belum Mengisi',
            'status_validasi' => 'Divalidasi'|'Menunggu'|'Ditolak'|null,
        ],
    ],
]
```

Aturan `status_validasi` per guru (meniru logika badge tabel lama):
- Semua jam terisi dan semua `status_jurnal` = `Divalidasi` → `Divalidasi`
- Semua jam terisi dan ada `Ditolak` → `Ditolak`
- Semua jam terisi selainnya (termasuk `status_jurnal` null) → `Menunggu`
- Ada jam kosong → `null` (ditampilkan sebagai `-` di kolom Validasi)

`status_jurnal` yang null tetap dianggap "terisi" agar tidak salah klasifikasi; ini perilaku sama
dengan tabel lama. Jurnal pengajuan izin (`adalah_pengajuan_izin`) juga ikut terhitung terisi,
sama seperti sekarang.

### 2. Ganti isi section `monitoring-jurnal`

Ganti tabel `<table>` di `⚡wakasek.blade.php:632-636` dengan tabel baru:

Header kolom: `Kelas | Jam Terisi | Status | Aksi`.

Setiap baris kelas:
- `wire:key="monitoring-kelas-{{ $item['id_kelas'] }}"`
- `x-show="!searchJurnal || $el.dataset.search.includes(searchJurnal)"`
- `data-search` = `mb_strtolower(nama_kelas + nama semua guru di kelas + mapel)` supaya pencarian
  berdasarkan nama guru atau mapel tetap menemukan baris kelasnya.
- Sel `Jam Terisi`: `{{ $item['terisi'] }}/{{ $item['total_jam'] }}`
- Sel `Status`: badge `Lengkap` (bg-success) / `Sebagian` (bg-warning text-dark) / `Kosong` (bg-danger)
- Tombol expand: dispatch `toggle-detail` dengan index `"kelas-{{ $item['id_kelas'] }}"` (string,
  **bukan** index integer, supaya tidak bentrok dengan `openDetails` milik section Rekap yang memakai
  integer). Isi tombol tetap dua span `x-show` seperti pola Rekap.

Baris detail (colspan 4) muncul saat `openDetails.includes('kelas-...')`, berisi tabel kecil:
`Guru | Mapel | Jam | Status Jurnal | Validasi` dengan badge yang sama seperti tabel lama
(`Sudah Mengisi` bg-success / `Belum Mengisi` bg-warning text-dark; validasi
bg-success/bg-danger/bg-warning text-dark).

`@forelse` kosong: pesan `Tidak ada kelas yang memiliki jadwal mengajar hari ini.`

Judul header section dan deskripsi disesuaikan menjadi
"Status pengisian dan validasi jurnal guru per kelas." agar akurat dengan isi tabel.

### 3. Tidak diubah

- `getMonitoringGuruProperty()` dan semua turunannya (`stats`, `jadwalMengajarSekarang`,
  `guruTanpaKeterangan`).
- `getRekapPerKelasProperty()`, `exportCsv()`, `laporanUntuk()`.
- Route, middleware, model, migration.

## Testing

Buat `tests/Feature/WakasekMonitoringJurnalPerKelasTest.php` (PHPUnit, `RefreshDatabase`),
mengikuti gaya `InputJurnalMultiJamTest.php`: buat `Pengguna`, `Kelas`, `Jadwal`, `Jurnal` langsung
lewat model karena belum ada factory untuk tabel-tabel ini.

1. `test_monitoring_jurnal_mengelompokkan_jurnal_per_kelas`
   - `travelTo` hari yang sudah pasti, buat 2 kelas, 2 guru, jadwal hari ini.
   - Login session `role => 'wakasek'`, `id_pengguna => $wakasek`.
   - `Livewire::test('wakasek')` → `assertSee` nama kedua kelas, `assertSee` nama kedua guru.
   - Ambil nilainya lewat `Livewire::test('wakasek')->instance()->getPropertyValue('monitoringJurnalPerKelas')`
     lalu assert: 2 item kelas, `terisi`/`kosong` benar per kelas.

2. `test_detail_guru_menandai_guru_yang_belum_mengisi`
   - Satu kelas, guru A punya 2 jam dan sudah mengisi keduanya; guru B punya 1 jam dan tidak mengisi.
   - Assert pada property: guru A `status_jurnal` = `Terisi Semua`, guru B = `Belum Mengisi`,
     `jam_kosong` guru B = `[3]`, `status_kelas` = `Sebagian`.
   - `assertSee('Belum Mengisi')` untuk memastikan badge benar-benar dirender.

3. `test_kelas_tanpa_jadwal_hari_ini_tidak_muncul`
   - Buat kelas tanpa jadwal; assert tidak ada di property.

4. Opsional: `test_monitoring_jurnal_menampilkan_guru_yang_sudah_divalidasi`
   - Jurnal dengan `status_validasi = 'Divalidasi'` → `status_validasi` guru = `Divalidasi`.

Jalankan: `php artisan test --compact --filter=WakasekMonitoringJurnalPerKelas`
lalu seluruh suite `php artisan test` untuk memastikan tidak ada test lain yang bergantung pada
struktur tabel lama.

Terakhir: `vendor/bin/pint --dirty --format agent`.

## Risiko & Edge Case

- **Guru mengajar beberapa jam di kelas yang sama**: muncul 1 baris detail dengan daftar jam
  (`Ke-1, Ke-2`), bukan beberapa baris.
- **Guru yang sama mengajar di dua kelas berbeda hari ini**: muncul di kedua baris kelas, sesuai
  model data sekarang (jurnal terikat guru+kelas+jam).
- **Guru tidak punya mapel_diampu**: tampil `-`, sama seperti tabel lama.
- **Jadwal hari ini tanpa guru valid**: sudah ter-cover join `pengguna` yang sudah ada di
  `getMonitoringGuruProperty()`.
- **Kolom `data-search` berubah**: tidak ada test yang bergantung pada isi `data-search`.

## Out of Scope

- Model jurnal diubah jadi satu entri per kelas per hari.
- Filter tanggal / periode di Monitoring Jurnal.
- Perubahan section Monitoring Guru, Rekap, Dispensasi, Pengajuan Izin, dan angka dashboard.
- Ekspor CSV / cetak untuk tampilan per kelas baru.
