# Plan: Guru & Sekretaris Shortcut Menu & Conditional Dispensasi Menu

## Overview
Two changes requested:
1. Add shortcut menu (tab pills) above header for **guru** and **sekretaris** roles, similar to admin and wakasek
2. Hide **Dispensasi** menu for guru who don't have piket duty today - only show when `session('is_guru_piket')` is true

---

## Current State Analysis

### Navigation Structure
- **Sidebar navigation** (in `layouts/app.blade.php:131-226`): Role-based nav items for all roles
- **Admin & Wakasek**: Have additional tab navigation (`<nav class="section-tabs">`) at top of their component views
- **Guru**: Dashboard component (`dashboard.blade.php`) has "MENU CEPAT" cards but no top tab navigation
- **Sekretaris**: Component (`sekretaris.blade.php`) uses URL query param `?menu=` for section switching but no top tab navigation

### Dispensasi Menu for Guru
- **Sidebar** (`app.blade.php:174`): Always shows for guru role
- **Conditional items** (`app.blade.php:177-182`): Extra items shown when `session('is_guru_piket')` is true
- **Dashboard "MENU CEPAT"** (`dashboard.blade.php:411-417`): Dispensasi card always shown
- **Guru Piket page** (`guru-piket.blade.php:107-119`): Button shown when `session('is_guru_piket')` is true

### Piket Check Logic
- `GuruPiketAccessService::bertugasHariIni()` checks both `GuruPiket` (weekly schedule) and `JadwalPiket` (specific date)
- Set in session at login (`login.blade.php:29-40`)

---

## Implementation Plan

### Task 1: Add Shortcut Menu (Tab Pills) for Guru
**File**: `resources/views/components/⚡dashboard.blade.php`

Add a `<nav class="section-tabs">` at the top of the guru dashboard section (before the welcome banner), similar to admin/wakasek:
- Tabs for: Dashboard, Jadwal Saya, Input Jurnal, Riwayat, Notifikasi, **Dispensasi (conditional)**, Piket Hari Ini (conditional), Rekapan (conditional)
- Use Alpine.js `x-data` with `activeSection` synced to URL hash
- Conditionally render Dispensasi, Piket Hari Ini, Rekapan tabs only when `session('is_guru_piket')` is true

### Task 2: Add Shortcut Menu (Tab Pills) for Sekretaris
**File**: `resources/views/components/⚡sekretaris.blade.php`

Add a `<nav class="section-tabs">` at the top of the sekretaris component (before the dashboard section):
- Tabs for: Dashboard, Data Kelas, Validasi Jurnal, Kehadiran, Tugas Guru, Surat Dispensasi, Rekap
- Use existing `activeSection` property and `bukaMenu()` method
- Style consistent with admin/wakasek tab-pill-group

### Task 3: Hide Dispensasi in Sidebar for Guru Without Piket
**File**: `resources/views/layouts/app.blade.php`

Modify the guru nav items (lines 166-182):
- Move `'dispensasi'` item inside the `if (session('is_guru_piket'))` block
- Update label to "Izin & Dispensasi" when piket (already done in line 179)
- Remove standalone dispensasi item from base guru nav items

### Task 4: Hide Dispensasi Card in Guru Dashboard "MENU CEPAT"
**File**: `resources/views/components/⚡dashboard.blade.php`

Wrap the Dispensasi menu card (lines 411-417) with `@if (session('is_guru_piket'))` condition

---

## Files to Modify

1. `resources/views/components/⚡dashboard.blade.php` - Add guru tab navigation + conditional Dispensasi card
2. `resources/views/components/⚡sekretaris.blade.php` - Add sekretaris tab navigation
3. `resources/views/layouts/app.blade.php` - Conditional Dispensasi in sidebar for guru

---

## Validation Steps

1. Login as guru **with** piket duty → Verify:
   - Top tab navigation shows all tabs including Dispensasi, Piket Hari Ini, Rekapan
   - Sidebar shows Dispensasi (as "Izin & Dispensasi"), Piket Hari Ini, Rekapan
   - Dashboard "MENU CEPAT" shows Dispensasi card

2. Login as guru **without** piket duty → Verify:
   - Top tab navigation shows only Dashboard, Jadwal Saya, Input Jurnal, Riwayat, Notifikasi
   - Sidebar does NOT show Dispensasi, Piket Hari Ini, Rekapan
   - Dashboard "MENU CEPAT" does NOT show Dispensasi card

3. Login as sekretaris → Verify:
   - Top tab navigation shows all sekretaris sections
   - Clicking tabs navigates correctly (uses existing `bukaMenu()`)

4. Run `vendor/bin/pint --dirty --format agent` to ensure code style compliance