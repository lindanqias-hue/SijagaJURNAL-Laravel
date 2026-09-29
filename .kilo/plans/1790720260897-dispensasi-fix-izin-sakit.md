# Revisi Dispensasi: Izin/Sakit Tidak Perlu Surat Dispensasi

## Masalah
1. Guru piket mengisi dispensasi (Izin/Sakit), tapi yang muncul di sekretaris adalah surat dispensasi untuk siswa yang izin/sakit.
2. Seharusnya Izin/Sakit tidak perlu dibuatkan surat dispensasi, cukup dikirim ke guru yang mengajar dan otomatis masuk absensi.

## Analisis Saat Ini
- Form dispensasi punya 3 jenis: Dispensasi, Izin, Sakit.
- Semua jenis mencatat ke tabel `dispensasi` dengan status:
  - Dispensasi: `Menunggu Persetujuan` (butuh validasi wakasek)
  - Izin/Sakit: langsung `Disetujui` (tanpa validasi wakasek)
- Sekretaris menampilkan semua `dispensasi` dengan status `Disetujui` hari ini (tanpa filter `ikonisurat`), sehingga Izin/Sakit ikut muncul.

## Solusi
1. **Filter sekretaris** – Hanya tampilkan `ikonisurat = 'Dispensasi'` di section Surat Dispensasi.
2. **Nonaktifkan akses surat untuk Izin/Sakit** – Cegah akses ke route surat-dispensasi untuk Izin/Sakit.
3. **Notifikasi** – Izin/Sakit tetap muncul di notifikasi guru (tanpa tombol lihat surat).
4. **Rekap** – Filter hanya `ikonisurat = 'Dispensasi'`.

## File yang Diubah

### 1. `resources/views/components/⚡sekretaris.blade.php`
- `getSuratDispensasiDisetujuiProperty`: tambahkan `->where('ikonisurat', 'Dispensasi')`
- `dashboard` kartu "Surat Dispensasi": hitung hanya yang `ikonisurat = 'Dispensasi'`

### 2. `app/Http/Controllers/ApprovalDispensasiController.php`
- `detail()`: abort 404 jika `ikonisurat !== 'Dispensasi'`
- `unduhUntukSekretaris()`: tambahkan filter `ikonisurat = 'Dispensasi'`
- `lihatUntukSekretaris()`: tambahkan filter `ikonisurat = 'Dispensasi'`

### 3. `resources/views/components/⚡notifikasi.blade.php`
- Sembunyikan tombol "Lihat Surat" untuk notifikasi dengan `ikonisurat !== 'Dispensasi'`

### 4. `resources/views/components/⚡rekap-dispensasi.blade.php`
- `getRekapProperty`: tambahkan filter `ikonisurat = 'Dispensasi'` (atau biarkan semua, sesuai kebutuhan rekap internal)

### 5. `resources/views/components/⚡dispensasi.blade.php`
- Tidak perlu perubahan besar, karena Izin/Sakit sudah:
  - Langsung disetujui (tanpa validasi wakasek)
  - Langsung sync ke absensi via `DispensasiJurnalService::sync`
  - Dikirim notifikasi ke guru via `dispensasi_penerima`

## Alur Baru
- **Dispensasi:** Guru piket buat → Kirim WA ke wakasek → Wakasek validasi → Muncul di sekretaris (dengan surat PDF).
- **Izin/Sakit:** Guru piket buat → Langsung masuk absensi → Notifikasi ke guru yang mengajar → Tidak muncul di sekretaris, tidak ada surat.

## Verification
- Buat Izin/Sakit → Pastikan tidak muncul di sekretaris, tapi muncul di notifikasi guru.
- Buat Dispensasi → Setelah wakasek setujui → Pastikan muncul di sekretaris dengan tombol lihat/unduh surat.