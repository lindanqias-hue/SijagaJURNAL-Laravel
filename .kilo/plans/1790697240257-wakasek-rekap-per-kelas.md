# Wakasek Rekap Jurnal Per Kelas - Implementation Plan

## Goal
Add a new "Rekap Per Kelas" tab in Wakasek Rekap section showing daily journal summary per class (e.g., XI RPL 1, XI RPL 2) with expandable period-level detail.

## UI/UX Design

### New Tab Location
- **In Rekap section** (`wakasek.blade.php` lines 570-595)
- New button: "Rekap Per Kelas" alongside existing: Riwayat Jurnal, Guru Belum Mengisi, Monitoring Kehadiran
- Uses same filter dropdown: Minggu ini / Bulan ini / Tahun ini

### View Structure
```
┌─ Rekap Per Kelas ────────────────────────────────────┐
│ Filter: [Minggu ini ▼] [Ekspor CSV] [Cetak]          │
├──────────────────────────────────────────────────────┤
│ XI RPL 1                    29 Sep 2026 (Selasa)     │
│ ├─ Total jam: 6    ✓ Terisi: 4    ⏳ Validasi: 1    │
│ │    ✗ Kosong: 1   ✓ Divalidasi: 3                  │
│ │    [▶ Detail]                                      │
│ ├──────────────────────────────────────────────────┤
│ XI RPL 2                    29 Sep 2026 (Selasa)     │
│ ├─ Total jam: 5    ✓ Terisi: 5    ⏳ Validasi: 0    │
│ │    ✗ Kosong: 0   ✓ Divalidasi: 5                  │
│ │    [▶ Detail]                                      │
│ ├──────────────────────────────────────────────────┤
│ XI RPL 1                    30 Sep 2026 (Rabu)       │
│ └─ ...                                               │
└──────────────────────────────────────────────────────┘
```

### Detail Modal/Expansion (click "Detail")
```
Detail XI RPL 1 - 29 Sep 2026
┌────┬────────────┬──────────┬────────────┬────────────┐
│ Jam│ Mapel/Guru │ Status   │ Validasi   │ Aksi       │
├────┼────────────┼──────────┼────────────┼────────────┤
│ 1  │ PPKn/Bpk A │ Terisi   │ Divalidasi │ [Lihat]    │
│ 2  │ B.Indo/Ibu │ Terisi   │ Menunggu   │ [Lihat]    │
│ 3  │ Matematika │ Kosong   │ -          │ -          │
│ 4  │ PKN/Bpk A  │ Terisi   │ Ditolak    │ [Lihat]    │
│ 5  │ Sejarah    │ Kosong   │ -          │ -          │
│ 6  │ Seni Budaya│ Terisi   │ Divalidasi │ [Lihat]    │
└────┴────────────┴──────────┴────────────┴────────────┘
```

## Data Model & Query Logic

### Core Query Pattern (follows existing `getRekapBelumMengisiProperty`)
For each date in filter range:
1. Get hari (Senin-Minggu)
2. Get all `Jadwal` for that hari → group by `id_kelas`
3. For each class, get all its `Jadwal` (periods) that day
4. For each period, check `Jurnal` exists (match guru+kelas+tanggal+jam_ke)
5. Aggregate counts per class-day

### Computed Properties Needed
```php
// New property in wakasek component
public string $filterRekapKelas = 'minggu'; // filter dropdown

public function getRekapPerKelasProperty(): Collection
{
    // Returns: Collection of class-day summaries
    // Each item: kelas, tanggal, hari, total_jam, terisi, menunggu_validasi, 
    //            kosong, divalidasi, detail (periods array)
}
```

### Period Detail Structure
```php
// For each period in class-day detail
[
    'jam_ke' => 1,
    'jam_mulai' => '07:00',
    'jam_selesai' => '07:45',
    'mapel' => 'PPKn',
    'guru' => 'Bpk A',
    'has_jurnal' => true,
    'status_validasi' => 'Divalidasi', // Menunggu, Divalidasi, Ditolak, null
    'id_jurnal' => 123,
]
```

## Component Changes (`wakasek.blade.php`)

### 1. Add Filter Property
```php
public string $filterRekapKelas = 'minggu';
```

### 2. Add `rekapTerbuka` Option
- Add `'rekap-kelas'` to allowed values in `bukaRekap()` validation (line 46)

### 3. Add Computed Property `rekapPerKelas`
- Query following pattern from `getRekapBelumMengisiProperty` (lines 214-243)
- Group by `id_kelas` + `tanggal`
- Compute aggregates

### 4. Add UI in Rekap Section
- New button in rekap selector grid (line 574-578)
- New `@if ($rekapTerbuka === 'rekap-kelas')` section (after line 593)
- Summary cards with expand toggle
- Detail view (modal or inline expansion)
- Toolbar: filter dropdown, Export CSV, Cetak

### 5. Export CSV
- Columns: Tanggal, Kelas, Hari, Total Jam, Terisi, Menunggu Validasi, Kosong, Divalidasi
- One row per class-day

### 6. Print View
- Extend `laporanUntuk()` and `dataCetak` to handle `'rekap-kelas'`
- Table format matching summary cards

## Reusable Logic
- Extract date range helper: `rentangTanggal()` already exists (line 397-406)
- Extract hari Indonesia mapping: already in `getRekapBelumMengisiProperty` (line 217)
- Reuse `Jadwal` + `Jurnal` query pattern

## Validation & Edge Cases
- Classes with no jadwal on a day → skip (don't show empty card)
- Filter "Tahun ini" → could be many rows; consider pagination or limit
- CSV export: handle large datasets with chunking
- Print: page break per class or per week

## Implementation Steps

1. **Backend (Component PHP)**
   - Add `filterRekapKelas` property
   - Add `rekapPerKelas` computed property
   - Update `bukaRekap()` validation
   - Add `exportCsv('rekap-kelas')` handling
   - Add `'rekap-kelas'` to `laporanUntuk()` and `dataCetak`

2. **Frontend (Blade Template)**
   - Add "Rekap Per Kelas" button in rekap selector
   - Add summary card rendering loop
   - Add detail expansion (Alpine.js x-show toggle)
   - Add toolbar with filter, export, print

3. **Testing**
   - Verify data matches existing rekap (cross-check totals)
   - Test filter changes (minggu/bulan/tahun)
   - Test CSV export format
   - Test print layout
   - Test empty states (no jadwal, no jurnal)

## Files to Modify
- `resources/views/components/⚡wakasek.blade.php` - main changes

## No Migration Needed
- Uses existing `jadwal`, `jurnal`, `kelas`, `pengguna` tables
- No schema changes required