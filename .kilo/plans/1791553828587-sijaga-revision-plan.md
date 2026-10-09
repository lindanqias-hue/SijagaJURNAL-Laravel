# SIJAGA Jurnal - Implementation Plan for 5-Team Member Revision

## Project Overview
This plan implements the task breakdown for revising the SIJAGA Jurnal website, divided among 5 team members based on file ownership to prevent Git conflicts. Each member works on separate files with clear boundaries.

---

## Team Assignments & File Ownership

| Member | Primary Files | New Files to Create |
|--------|---------------|---------------------|
| **Orang 1** | `guru-piket.blade.php`, `admin.blade.php` | `piket-kehadiran.blade.php`, `piket-detail.blade.php` |
| **Orang 2** | `input-jurnal.blade.php`, `riwayat.blade.php` | `jurnal-detail.blade.php`, `Services/JurnalGroupingService.php` |
| **Orang 3** | `wakasek.blade.php` | `izin-guru.blade.php`, `Models/IzinGuru.php`, migration `create_izin_guru_table` |
| **Orang 4** | `dispensasi.blade.php`, `rekap-dispensasi.blade.php`, `approve-dispensasi.blade.php`, `sekretaris.blade.php`, `surat-dispensasi.blade.php`, `surat-dispensasi-pdf.blade.php` | `dispensasi-detail.blade.php`, `wakasek-dispensasi.blade.php` |
| **Orang 5** | `layouts/app.blade.php`, `components/app-modal.blade.php`, `routes/web.php`, `dashboard.blade.php`, `notifikasi.blade.php`, `login.blade.php` | `components/layouts/detail-page.blade.php` |

---

## Phase 1: Day 1 - Foundation (Orang 5 Priority)

### 1.1 Routes & Menu Setup (Orang 5)
- [ ] Add all new routes to `routes/web.php` (already partially done - verify completeness)
- [ ] Update `NavMenu.php` with new menu groups for all roles
- [ ] Create reusable detail page component: `components/layouts/detail-page.blade.php`
- [ ] Define route naming convention agreement with team

### 1.2 Shared Models & Services (Orang 2 & 3)
- [ ] **Orang 2**: Create `JurnalGroupingService.php` for grouping journals by guru/kelas/tanggal
- [ ] **Orang 3**: Create `IzinGuru` model + migration `create_izin_guru_table`
- [ ] **Orang 3**: Expose `isGuruIzin()` method for Orang 1 & 2 to use

### 1.3 Merge Order Day 1
1. Orang 5 merges routes/menu/component first
2. Orang 2 merges JurnalGroupingService
3. Orang 3 merges IzinGuru model/migration

---

## Phase 2: Parallel Development (Days 2-5)

### Orang 1: Guru Piket & Jadwal Piket
**Files: `guru-piket.blade.php`, `admin.blade.php` (piket sections), new `piket-kehadiran.blade.php`, `piket-detail.blade.php`**

#### Tasks:
1. **Fix jadwal piket sorting** - Default to newest first, human-readable date labels
2. **Refactor status jadwal hierarchy** - Order: hari/tanggal → kelas → jam → guru
3. **Split piket view into two perspectives**:
   - Tab 1: Per Kelas view
   - Tab 2: Per Guru view
4. **Move long info from modals to detail pages** - Use new `detail-page` component
5. **Implement auto "Belum Terisi" detection** - When jam lewat without jurnal/izin, show in piket view
6. **Piket action flow**:
   - Hadir tapi lupa → send reminder (deadline end of day)
   - Tidak hadir tanpa izin → mark "Tanpa Keterangan", assign substitute/give tasks
   - End of day auto → "Tidak Mengisi" → goes to wakasek rekap
7. **Create student attendance form** (piket only) - writes to existing `absensi_siswa`/`keterangan_siswa` tables

### Orang 2: Input Jurnal & Riwayat (Guru Side)
**Files: `input-jurnal.blade.php`, `riwayat.blade.php`, new `jurnal-detail.blade.php`, `Services/JurnalGroupingService.php`**

#### Tasks:
1. **Group journals by guru + kelas + tanggal** - Single unit per teacher per class per day
2. **Create `JurnalGroupingService`** - Reusable service for Orang 3 validation
3. **Form changes**:
   - Remove "Sakit" keterangan requirement
   - Remove student attendance input from jurnal form
   - Add read-only student attendance display (from piket data)
   - Remove guru izin section → link to new `izin-guru` page
4. **Riwayat**: Move long jurnal info from modal to `jurnal-detail` page
5. **Use `JurnalGroupingService`** for all grouping logic

### Orang 3: Izin Guru & Validasi Jurnal
**Files: `wakasek.blade.php`, new `izin-guru.blade.php`, `Models/IzinGuru.php`**

#### Tasks:
1. **Build new `izin-guru` page** (not editing input-jurnal):
   - Jenis izin: Izin, Sakit, Dinas Luar Kota, Cuti
   - Rentang jam selection (e.g., jam 1-5)
   - Tugas per jam group (not per jam)
   - Half-day / full-day shortcuts → auto-create entries for all scheduled jam
2. **New `izin_guru` table** - separate from jurnal table
3. **Wakasek approval** in `wakasek.blade.php`:
   - Group jam by actual schedule, order from first jam
   - Use `JurnalGroupingService` from Orang 2
4. **Remove dispensasi section from wakasek** → replace with link to `wakasek-dispensasi` (Orang 4)

### Orang 4: Dispensasi & Surat
**Files: `dispensasi.blade.php`, `rekap-dispensasi.blade.php`, `approve-dispensasi.blade.php`, `sekretaris.blade.php`, `surat-dispensasi.blade.php`, `surat-dispensasi-pdf.blade.php`, new `dispensasi-detail.blade.php`, `wakasek-dispensasi.blade.php`**

#### Tasks:
1. **Cross-class dispensasi** - Single submission for multiple classes/students
   - Restructure data: one dispensasi → many recipients (via `dispensasi_penerima`)
2. **Surat distinction**:
   - Approved surat: show wakasek name + guru piket name
   - Both names visible in rekap
   - Approver names tied to single submission
3. **Create `wakasek-dispensasi` component** - replaces dispensasi section in wakasek
4. **Move long dispensasi info from modals to detail pages**
5. **Check `sekretaris.blade.php`** - if any izin/validasi jurnal changes needed, coordinate with Orang 3

---

## Phase 3: Integration & Merge Order (Day 6+)

### Merge Sequence:
1. **Orang 3** - Merge first (removes old dispensasi from wakasek, adds izin-guru)
2. **Orang 2** - Merge jurnal changes (uses Orang 3's service, removes izin from input-jurnal)
3. **Orang 1** - Merge piket changes (uses Orang 3's izin check, writes student attendance)
4. **Orang 4** - Merge dispensasi (uses new structure, wakasek-dispensasi component)
5. **Orang 5** - Final integration: dashboard, notifications, cleanup, testing

---

## Database Migrations (Each Member Creates Own)

| Member | Migration Files |
|--------|-----------------|
| Orang 1 | `create_piket_kehadiran_siswa_table` (if new table needed), `add_status_piket_to_jadwal_piket_table` |
| Orang 2 | `add_grouping_fields_to_jurnal_table` (if needed) |
| Orang 3 | `create_izin_guru_table` (NEW - main migration) |
| Orang 4 | `add_penerima_fields_to_dispensasi_table`, `add_approver_names_to_dispensasi_table` |
| Orang 5 | None (handles routes/menu only) |

---

## Key Coordination Points (Day 1 Agreements)

### 1. Student Attendance Data
- **Orang 1** writes to `absensi_siswa` + `keterangan_siswa`
- **Orang 2** reads only (read-only display in jurnal form)
- No table structure changes needed

### 2. Izin Data Access
- **Orang 3** provides `IzinGuru` model + `isGuruIzin(guruId, tanggal, jamKe)` method by Day 1
- **Orang 1** uses it to determine if class empty due to izin
- **Orang 2** does NOT touch izin data

### 3. Jurnal Grouping Service
- **Orang 2** creates `JurnalGroupingService::groupByGuruKelasTanggal()`
- **Orang 3** uses it in wakasek validation
- Service returns: `Collection` grouped by `guru_id` → `kelas_id` → `tanggal` → `journals[]`

### 4. Dispensasi in Wakasek
- **Orang 4** creates `wakasek-dispensasi` component
- **Orang 3** removes old dispensasi code from `wakasek.blade.php` and includes new component

### 5. Route Names (Agreed Day 1)
| Feature | Route Name | Component |
|---------|------------|-----------|
| Izin Guru | `izin-guru` | `izin-guru` |
| Piket Kehadiran Siswa | `piket-kehadiran` | `piket-kehadiran` |
| Jurnal Detail | `jurnal-detail` | `jurnal-detail` |
| Dispensasi Detail | `dispensasi-detail` | `dispensasi-detail` |
| Wakasek Dispensasi | `wakasek-dispensasi` | `wakasek-dispensasi` |

---

## Validation & Testing Checklist

### Per Member:
- [ ] Run `vendor/bin/pint --format agent` on modified files
- [ ] Run `php artisan test --filter=<relevant_tests>`
- [ ] Verify no migration conflicts (each creates own)
- [ ] Test with all roles (guru, wakasek, admin, sekretaris, piket)

### Integration Testing (Orang 5 leads):
- [ ] Full flow: Guru input jurnal → Wakasek validasi → Sekretaris konfirmasi
- [ ] Izin flow: Guru ajukan → Wakasek setujui → Sync ke jurnal
- [ ] Piket flow: Kelas kosong → Piket tindak lanjut → Rekap wakasek
- [ ] Dispensasi cross-class: Single submit → Multiple recipients → Surat with both approvers
- [ ] Dashboard & notifications show correct data per role
- [ ] Mobile responsive (sidebar accordion, section tabs)

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| File conflicts on `wakasek.blade.php` (Orang 3 & 4) | Orang 3 removes dispensasi section FIRST, Orang 4 adds component after |
| Route conflicts | Orang 5 owns `routes/web.php` exclusively |
| Menu conflicts | Orang 5 owns `NavMenu.php` exclusively |
| Shared table `absensi_siswa` | Clear ownership: Orang 1 writes, Orang 2 reads |
| Service dependency (JurnalGroupingService) | Orang 2 delivers service Day 1, Orang 3 consumes Day 2+ |

---

## Git Branching Strategy

| Member | Branch Name |
|--------|-------------|
| Orang 1 | `paket-1-piket` |
| Orang 2 | `paket-2-jurnal` |
| Orang 3 | `paket-3-izin` |
| Orang 4 | `paket-4-dispensasi` |
| Orang 5 | `paket-5-navigasi` |

**Merge order**: paket-5-navigasi → paket-3-izin → paket-2-jurnal → paket-1-piket → paket-4-dispensasi → main

---

## Out of Scope
- `.kilo/` folder cleanup (Orang 5 at end)
- Backup file removal (Orang 5 at end)
- Major refactoring of existing working features not mentioned in task breakdown
- New UI framework migration (stay with Bootstrap + custom CSS)

---

## Next Steps
1. **Day 1 Kickoff**: All members review their files, confirm ownership, agree on route names
2. **Orang 5**: Complete routes, menu, detail-page component
3. **Orang 2 & 3**: Deliver shared service + model
4. **Parallel work** begins Day 2
5. **Daily standup** to sync on coordination points