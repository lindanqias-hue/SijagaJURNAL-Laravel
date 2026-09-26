<?php

use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $pencarianPengguna = '';
    public string $filterRole = '';
    public string $pencarianSiswa = '';
    public string $pencarianKelas = '';
    public string $pencarianJadwal = '';
    public string $pencarianPiket = '';
    public string $pencarianJurnal = '';
    public string $pencarianDispensasi = '';

    public bool $showModal = false;
    public string $modalType = '';
    public ?int $editingId = null;
    public array $form = [];

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const ROLE = ['admin', 'wakasek', 'guru', 'sekretaris'];
    private const STATUS_KEPEGAWAIAN = ['PNS', 'PPPK', 'Honorer'];
    private const STATUS_PIKET = ['Aktif', 'Tidak Aktif'];

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        abort_unless(session('role') === 'admin', 403);
    }

    public function getStatsProperty(): array
    {
        return [
            'pengguna' => Pengguna::count(),
            'guru' => Pengguna::where('role', 'guru')->count(),
            'siswa' => DB::table('siswa')->count(),
            'kelas' => DB::table('kelas')->count(),
            'jadwalHariIni' => Jadwal::where('hari', now('Asia/Jakarta')->locale('id')->translatedFormat('l'))->count(),
            'jurnalHariIni' => Jurnal::whereDate('tanggal', now('Asia/Jakarta')->toDateString())->count(),
            'piketHariIni' => DB::table('jadwal_piket')
                ->whereDate('tanggal', now('Asia/Jakarta')->toDateString())
                ->where('status', 'Aktif')
                ->count(),
            'dispensasiMenunggu' => DB::table('dispensasi')
                ->where('status', 'Menunggu Persetujuan')
                ->count(),
        ];
    }

    public function getPenggunaProperty()
    {
        return Pengguna::query()
            ->leftJoin('kelas', 'pengguna.id_kelas', '=', 'kelas.id_kelas')
            ->select([
                'pengguna.id_pengguna',
                'pengguna.nama',
                'pengguna.nip',
                'pengguna.role',
                'pengguna.mapel_diampu',
                'pengguna.status_kepegawaian',
                'pengguna.no_hp',
                'kelas.nama_kelas',
            ])
            ->when($this->pencarianPengguna !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('nama', 'like', '%'.$this->pencarianPengguna.'%')
                        ->orWhere('nip', 'like', '%'.$this->pencarianPengguna.'%');
                });
            })
            ->when($this->filterRole !== '', fn ($query) => $query->where('role', $this->filterRole))
            ->orderBy('role')
            ->orderBy('nama')
            ->paginate(10);
    }

    public function updatingPencarianPengguna(): void
    {
        $this->resetPage();
        $this->resetPage('guruPage');
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingPencarianSiswa(): void
    {
        $this->resetPage('siswaPage');
    }

    public function updatingPencarianKelas(): void
    {
        $this->resetPage('kelasPage');
    }

    public function updatingPencarianJadwal(): void
    {
        $this->resetPage('jadwalPage');
    }

    public function updatingPencarianPiket(): void
    {
        $this->resetPage('piketPage');
    }

    public function updatingPencarianJurnal(): void
    {
        $this->resetPage('jurnalPage');
    }

    public function updatingPencarianDispensasi(): void
    {
        $this->resetPage('dispensasiPage');
    }

    public function openCreate(string $type): void
    {
        $this->ensureAdmin();
        $this->modalType = $type;
        $this->editingId = null;
        $this->form = $this->emptyForm($type);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(string $type, int $id): void
    {
        $this->ensureAdmin();
        $this->modalType = $type;
        $this->editingId = $id;
        $this->form = match ($type) {
            'pengguna' => $this->penggunaForm(Pengguna::findOrFail($id)),
            'siswa' => $this->siswaForm(Siswa::findOrFail($id)),
            'kelas' => $this->kelasForm(Kelas::findOrFail($id)),
            'jadwal' => $this->jadwalForm(Jadwal::findOrFail($id)),
            'piket' => $this->piketForm(JadwalPiket::findOrFail($id)),
            default => abort(404),
        };
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->ensureAdmin();
        $validated = $this->validate($this->rules(), [], $this->attributes());

        match ($this->modalType) {
            'pengguna' => $this->savePengguna($validated['form']),
            'siswa' => $this->saveSiswa($validated['form']),
            'kelas' => $this->saveKelas($validated['form']),
            'jadwal' => $this->saveJadwal($validated['form']),
            'piket' => $this->savePiket($validated['form']),
            default => abort(404),
        };

        $this->closeModal();
        session()->flash('success', 'Data berhasil '.($this->editingId ? 'diperbarui.' : 'ditambahkan.'));
    }

    public function delete(string $type, int $id): void
    {
        $this->ensureAdmin();

        $deleted = match ($type) {
            'pengguna' => $this->deletePengguna($id),
            'siswa' => $this->deleteSiswa($id),
            'kelas' => $this->deleteKelas($id),
            'jadwal' => $this->deleteJadwal($id),
            'piket' => (bool) JadwalPiket::findOrFail($id)->delete(),
            default => abort(404),
        };

        if ($deleted) {
            session()->flash('success', 'Data berhasil dihapus.');
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(session('role') === 'admin', 403);
    }

    private function emptyForm(string $type): array
    {
        return match ($type) {
            'pengguna' => ['nama' => '', 'nip' => '', 'role' => 'guru', 'mapel_diampu' => '', 'status_kepegawaian' => '', 'no_hp' => '', 'id_kelas' => '', 'password' => ''],
            'siswa' => ['nama_siswa' => '', 'id_kelas' => ''],
            'kelas' => ['nama_kelas' => '', 'wali_kelas' => ''],
            'jadwal' => ['id_guru' => '', 'id_kelas' => '', 'hari' => 'Senin', 'jam_ke' => '', 'jam_mulai' => '', 'jam_selesai' => ''],
            'piket' => ['id_guru' => '', 'tanggal' => now('Asia/Jakarta')->toDateString(), 'jam_mulai' => '', 'jam_selesai' => '', 'status' => 'Aktif', 'keterangan' => ''],
            default => abort(404),
        };
    }

    private function penggunaForm(Pengguna $pengguna): array { return ['nama' => $pengguna->nama, 'nip' => $pengguna->nip, 'role' => $pengguna->role, 'mapel_diampu' => $pengguna->mapel_diampu ?? '', 'status_kepegawaian' => $pengguna->status_kepegawaian ?? '', 'no_hp' => $pengguna->no_hp ?? '', 'id_kelas' => $pengguna->id_kelas ?? '', 'password' => '']; }
    private function siswaForm(Siswa $siswa): array { return ['nama_siswa' => $siswa->nama_siswa, 'id_kelas' => $siswa->id_kelas]; }
    private function kelasForm(Kelas $kelas): array { return ['nama_kelas' => $kelas->nama_kelas, 'wali_kelas' => $kelas->wali_kelas ?? '']; }
    private function jadwalForm(Jadwal $jadwal): array { return ['id_guru' => $jadwal->id_guru, 'id_kelas' => $jadwal->id_kelas, 'hari' => $jadwal->hari, 'jam_ke' => $jadwal->jam_ke, 'jam_mulai' => substr($jadwal->jam_mulai, 0, 5), 'jam_selesai' => substr($jadwal->jam_selesai, 0, 5)]; }
    private function piketForm(JadwalPiket $piket): array { return ['id_guru' => $piket->id_guru, 'tanggal' => $piket->tanggal->toDateString(), 'jam_mulai' => substr($piket->jam_mulai, 0, 5), 'jam_selesai' => substr($piket->jam_selesai, 0, 5), 'status' => $piket->status, 'keterangan' => $piket->keterangan ?? '']; }

    private function rules(): array
    {
        return match ($this->modalType) {
            'pengguna' => ['form.nama' => ['required', 'string', 'max:100'], 'form.nip' => ['required', 'string', 'max:30', Rule::unique('pengguna', 'nip')->ignore($this->editingId, 'id_pengguna')], 'form.role' => ['required', Rule::in(self::ROLE)], 'form.mapel_diampu' => ['nullable', 'string', 'max:100'], 'form.status_kepegawaian' => ['nullable', Rule::in(self::STATUS_KEPEGAWAIAN)], 'form.no_hp' => ['nullable', 'string', 'max:20'], 'form.id_kelas' => ['nullable', 'exists:kelas,id_kelas'], 'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8', 'max:100']],
            'siswa' => ['form.nama_siswa' => ['required', 'string', 'max:100'], 'form.id_kelas' => ['required', 'exists:kelas,id_kelas']],
            'kelas' => ['form.nama_kelas' => ['required', 'string', 'max:50'], 'form.wali_kelas' => ['nullable', 'string', 'max:100']],
            'jadwal' => ['form.id_guru' => ['required', Rule::exists('pengguna', 'id_pengguna')->where('role', 'guru')], 'form.id_kelas' => ['required', 'exists:kelas,id_kelas'], 'form.hari' => ['required', Rule::in(self::HARI)], 'form.jam_ke' => ['required', 'integer', 'min:1', 'max:20'], 'form.jam_mulai' => ['required', 'date_format:H:i'], 'form.jam_selesai' => ['required', 'date_format:H:i', 'after:form.jam_mulai']],
            'piket' => ['form.id_guru' => ['required', Rule::exists('pengguna', 'id_pengguna')->where('role', 'guru')], 'form.tanggal' => ['required', 'date'], 'form.jam_mulai' => ['required', 'date_format:H:i'], 'form.jam_selesai' => ['required', 'date_format:H:i', 'after:form.jam_mulai'], 'form.status' => ['required', Rule::in(self::STATUS_PIKET)], 'form.keterangan' => ['nullable', 'string', 'max:255']],
            default => abort(404),
        };
    }

    private function attributes(): array { return ['form.nama' => 'nama', 'form.nip' => 'NIP/ID', 'form.id_kelas' => 'kelas', 'form.id_guru' => 'guru', 'form.jam_ke' => 'jam ke', 'form.jam_mulai' => 'jam mulai', 'form.jam_selesai' => 'jam selesai']; }

    private function savePengguna(array $data): void
    {
        $password = $data['password']; unset($data['password']);
        $data['id_kelas'] = $data['id_kelas'] ?: null; $data['status_kepegawaian'] = $data['status_kepegawaian'] ?: null; $data['mapel_diampu'] = $data['mapel_diampu'] ?: null; $data['no_hp'] = $data['no_hp'] ?: null;
        if ($password !== '') { $data['password'] = Hash::make($password); }
        if ($this->editingId) { Pengguna::findOrFail($this->editingId)->update($data); } else { Pengguna::create($data); }
    }

    private function saveSiswa(array $data): void
    {
        DB::transaction(function () use ($data): void { $oldKelasId = $this->editingId ? Siswa::findOrFail($this->editingId)->id_kelas : null; $siswa = $this->editingId ? tap(Siswa::findOrFail($this->editingId))->update($data) : Siswa::create($data); $this->syncJumlahSiswa(array_filter([$oldKelasId, $siswa->id_kelas])); });
    }

    private function saveKelas(array $data): void { $data['wali_kelas'] = $data['wali_kelas'] ?: null; if ($this->editingId) { Kelas::findOrFail($this->editingId)->update($data); } else { Kelas::create($data); } }
    private function saveJadwal(array $data): void { if ($this->editingId) { Jadwal::findOrFail($this->editingId)->update($data); } else { Jadwal::create($data); } }
    private function savePiket(array $data): void { $data['keterangan'] = $data['keterangan'] ?: null; if ($this->editingId) { JadwalPiket::findOrFail($this->editingId)->update($data); } else { JadwalPiket::create($data); } }

    private function deletePengguna(int $id): bool
    {
        if ((int) session('id_pengguna') === $id) {
            $this->addError('delete', 'Akun admin yang sedang digunakan tidak dapat dihapus.');

            return false;
        }

        return $this->blockIfUsed($id, 'pengguna', [['jadwal', 'id_guru'], ['jurnal', 'id_guru'], ['jurnal', 'id_validator'], ['jurnal', 'id_sekretaris'], ['guru_piket', 'id_pengguna'], ['jadwal_piket', 'id_guru'], ['dispensasi', 'id_guru_piket'], ['dispensasi', 'id_guru'], ['dispensasi', 'id_wakasek'], ['dispensasi_penerima', 'id_guru'], ['kehadiran_gurus', 'id_guru']]);
    }
    private function deleteSiswa(int $id): bool { return $this->blockIfUsed($id, 'siswa', [['absensi_siswa', 'id_siswa'], ['keterangan_siswa', 'id_siswa'], ['dispensasi', 'id_siswa']]); }
    private function deleteKelas(int $id): bool { return $this->blockIfUsed($id, 'kelas', [['siswa', 'id_kelas'], ['pengguna', 'id_kelas'], ['jadwal', 'id_kelas'], ['jurnal', 'id_kelas'], ['dispensasi', 'id_kelas'], ['schedules', 'id_kelas'], ['jadwal_pelajarans', 'kelas_id']]); }
    private function deleteJadwal(int $id): bool { return $this->blockIfUsed($id, 'jadwal', [['kehadiran_gurus', 'id_jadwal'], ['dispensasi', 'id_jadwal']]); }
    private function blockIfUsed(int $id, string $modelTable, array $references): bool
    {
        foreach ($references as [$table, $column]) { if (DB::table($table)->where($column, $id)->exists()) { $this->addError('delete', 'Data tidak dapat dihapus karena masih digunakan oleh data operasional.'); return false; } }
        match ($modelTable) { 'pengguna' => Pengguna::findOrFail($id)->delete(), 'siswa' => DB::transaction(function () use ($id): void { $siswa = Siswa::findOrFail($id); $kelasId = $siswa->id_kelas; $siswa->delete(); $this->syncJumlahSiswa([$kelasId]); }), 'kelas' => Kelas::findOrFail($id)->delete(), 'jadwal' => Jadwal::findOrFail($id)->delete() };
        return true;
    }
    private function syncJumlahSiswa(array $kelasIds): void { foreach (array_unique($kelasIds) as $kelasId) { Kelas::whereKey($kelasId)->update(['jumlah_siswa' => Siswa::where('id_kelas', $kelasId)->count()]); } }

    public function getSiswaProperty()
    {
        return DB::table('siswa')
            ->leftJoin('kelas', 'siswa.id_kelas', '=', 'kelas.id_kelas')
            ->select(['siswa.id_siswa', 'siswa.nama_siswa', 'kelas.nama_kelas'])
            ->when($this->pencarianSiswa !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('siswa.nama_siswa', 'like', '%'.$this->pencarianSiswa.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianSiswa.'%');
                });
            })
            ->orderBy('siswa.nama_siswa')
            ->paginate(10, ['*'], 'siswaPage');
    }

    public function getGuruProperty()
    {
        return Pengguna::query()
            ->where('role', 'guru')
            ->when($this->pencarianPengguna !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('nama', 'like', '%'.$this->pencarianPengguna.'%')
                        ->orWhere('nip', 'like', '%'.$this->pencarianPengguna.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(10, ['*'], 'guruPage');
    }

    public function getKelasProperty()
    {
        return DB::table('kelas')
            ->select(['id_kelas', 'nama_kelas', 'wali_kelas'])
            ->when($this->pencarianKelas !== '', fn ($query) => $query->where('nama_kelas', 'like', '%'.$this->pencarianKelas.'%'))
            ->orderBy('nama_kelas')
            ->paginate(10, ['*'], 'kelasPage');
    }

    public function getGuruOptionsProperty()
    {
        return Pengguna::query()->where('role', 'guru')->orderBy('nama')->get(['id_pengguna', 'nama']);
    }

    public function getKelasOptionsProperty()
    {
        return Kelas::query()->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas']);
    }

    public function getJurnalTerbaruProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->limit(8)
            ->get();
    }

    public function getJadwalPiketHariIniProperty()
    {
        return DB::table('jadwal_piket')
            ->join('pengguna', 'jadwal_piket.id_guru', '=', 'pengguna.id_pengguna')
            ->select([
                'jadwal_piket.*',
                'pengguna.nama',
            ])
            ->when($this->pencarianPiket !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('pengguna.nama', 'like', '%'.$this->pencarianPiket.'%')
                        ->orWhere('jadwal_piket.status', 'like', '%'.$this->pencarianPiket.'%')
                        ->orWhere('jadwal_piket.keterangan', 'like', '%'.$this->pencarianPiket.'%');
                });
            })
            ->orderByDesc('jadwal_piket.tanggal')
            ->orderBy('pengguna.nama')
            ->paginate(10, ['*'], 'piketPage');
    }

    public function getJadwalMengajarHariIniProperty()
    {
        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->join('kelas', 'jadwal.id_kelas', '=', 'kelas.id_kelas')
            ->select([
                'jadwal.*',
                'pengguna.nama as nama_guru',
                'pengguna.mapel_diampu',
                'kelas.nama_kelas',
            ])
            ->when($this->pencarianJadwal !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('pengguna.nama', 'like', '%'.$this->pencarianJadwal.'%')
                        ->orWhere('pengguna.mapel_diampu', 'like', '%'.$this->pencarianJadwal.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianJadwal.'%');
                });
            })
            ->orderBy('jadwal.hari')
            ->orderBy('jadwal.jam_ke')
            ->paginate(10, ['*'], 'jadwalPage');
    }
    public function getJurnalProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->when($this->pencarianJurnal !== '', function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('guru', fn ($query) => $query->where('nama', 'like', '%'.$this->pencarianJurnal.'%'))
                        ->orWhereHas('kelas', fn ($query) => $query->where('nama_kelas', 'like', '%'.$this->pencarianJurnal.'%'));
                });
            })
            ->orderBy('tanggal')
            ->orderBy('id_jurnal')
            ->paginate(10, ['*'], 'jurnalPage');
    }

    public function getDispensasiProperty()
    {
        return DB::table('dispensasi')
            ->leftJoin('siswa', 'dispensasi.id_siswa', '=', 'siswa.id_siswa')
            ->leftJoin('kelas', 'dispensasi.id_kelas', '=', 'kelas.id_kelas')
            ->select(['dispensasi.id_dispensasi', 'dispensasi.tanggal', 'dispensasi.jenis_dispensasi', 'dispensasi.alasan', 'dispensasi.status', 'siswa.nama_siswa', 'kelas.nama_kelas'])
            ->when($this->pencarianDispensasi !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('siswa.nama_siswa', 'like', '%'.$this->pencarianDispensasi.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianDispensasi.'%')
                        ->orWhere('dispensasi.status', 'like', '%'.$this->pencarianDispensasi.'%');
                });
            })
            ->orderBy('dispensasi.tanggal')
            ->orderBy('dispensasi.id_dispensasi')
            ->paginate(10, ['*'], 'dispensasiPage');
    }
};
?>

<style>
    [x-cloak] { display: none !important; }
</style>

<div x-data="{
    activeSection: 'admin',
    syncSection() {
        const section = window.location.hash.slice(1);
        this.activeSection = ['pengguna', 'guru', 'siswa', 'kelas', 'jadwal', 'jadwal-piket', 'jurnal', 'dispensasi'].includes(section) ? section : 'admin';
    },
    openSection(section, role = '') {
        if (section === 'pengguna') {
            $wire.set('filterRole', role);
        }

        this.activeSection = section;
        window.location.hash = section;
    }
}" x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>
    @endif
    @error('delete')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section id="admin" x-cloak x-show="activeSection === 'admin'">
        <div class="welcome-banner mb-4">
            <div class="fw-bold" style="font-size:22px; color:#fff;">Dashboard Admin</div>
            <div style="color:rgba(255,255,255,.7); font-size:13px; margin-top:6px;">Administrasi pengguna, jadwal, jurnal, dan dispensasi sekolah.</div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('pengguna')"><div class="text-muted small">Total Pengguna</div><div class="stat-value">{{ $this->stats['pengguna'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('pengguna', 'guru')"><div class="text-muted small">Guru</div><div class="stat-value">{{ $this->stats['guru'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('siswa')"><div class="text-muted small">Siswa</div><div class="stat-value">{{ $this->stats['siswa'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('kelas')"><div class="text-muted small">Kelas</div><div class="stat-value">{{ $this->stats['kelas'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('dispensasi')"><div class="text-muted small">Dispensasi Menunggu</div><div class="stat-value">{{ $this->stats['dispensasiMenunggu'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('jadwal')"><div class="text-muted small">Jadwal Hari Ini</div><div class="stat-value">{{ $this->stats['jadwalHariIni'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('jurnal')"><div class="text-muted small">Jurnal Hari Ini</div><div class="stat-value">{{ $this->stats['jurnalHariIni'] }}</div></button></div>
            <div class="col-6 col-lg-3"><button type="button" class="stat-card border-0 text-start w-100" style="cursor:pointer;" x-on:click="openSection('jadwal-piket')"><div class="text-muted small">Guru Piket Hari Ini</div><div class="stat-value">{{ $this->stats['piketHariIni'] }}</div></button></div>
        </div>
    </section>

    <section id="pengguna" x-cloak x-show="activeSection === 'pengguna'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span>Daftar Pengguna</span><button type="button" class="btn btn-sm btn-app-primary" wire:click="openCreate('pengguna')">Tambah Pengguna</button></div>
        <div class="p-3 border-bottom"><div class="row g-2">
            <div class="col-md-8"><input type="search" wire:model.live.debounce.300ms="pencarianPengguna" class="form-control" placeholder="Cari nama atau NIP/ID pengguna..."></div>
            <div class="col-md-4"><select wire:model.live="filterRole" class="form-select"><option value="">Semua Role</option><option value="admin">Admin</option><option value="wakasek">Wakasek</option><option value="guru">Guru</option><option value="sekretaris">Sekretaris</option></select></div>
        </div></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>NIP/ID</th><th>Role</th><th>Status Kepegawaian</th><th>No. HP</th><th>Kelas</th><th>Mapel</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse ($this->pengguna as $user)
                <tr wire:key="admin-user-{{ $user->id_pengguna }}"><td>{{ $user->nama }}</td><td>{{ $user->nip }}</td><td><span class="badge bg-light text-dark">{{ $user->role }}</span></td><td>{{ $user->status_kepegawaian ?? '-' }}</td><td>{{ $user->no_hp ?? '-' }}</td><td>{{ $user->nama_kelas ?? '-' }}</td><td>{{ $user->mapel_diampu ?: '-' }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEdit('pengguna', {{ $user->id_pengguna }})">Edit</button> <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete('pengguna', {{ $user->id_pengguna }})" wire:confirm="Hapus pengguna ini?">Hapus</button></td></tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengguna yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->pengguna->links() }}</div>
    </section>

    <section id="guru" x-cloak x-show="activeSection === 'guru'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Daftar Guru</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianPengguna" class="form-control" placeholder="Cari nama atau NIP guru..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>NIP</th><th>Status Kepegawaian</th><th>No. HP</th><th>Mapel</th></tr></thead><tbody>
            @forelse ($this->guru as $guru)<tr wire:key="admin-guru-{{ $guru->id_pengguna }}"><td>{{ $guru->nama }}</td><td>{{ $guru->nip }}</td><td>{{ $guru->status_kepegawaian ?? '-' }}</td><td>{{ $guru->no_hp ?? '-' }}</td><td>{{ $guru->mapel_diampu ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada guru yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->guru->links() }}</div>
    </section>

    <section id="siswa" x-cloak x-show="activeSection === 'siswa'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span>Daftar Siswa</span><button type="button" class="btn btn-sm btn-app-primary" wire:click="openCreate('siswa')">Tambah Siswa</button></div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianSiswa" class="form-control" placeholder="Cari nama siswa atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>Kelas</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse ($this->siswa as $siswa)<tr wire:key="admin-siswa-{{ $siswa->id_siswa }}"><td>{{ $siswa->nama_siswa }}</td><td>{{ $siswa->nama_kelas ?? '-' }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEdit('siswa', {{ $siswa->id_siswa }})">Edit</button> <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete('siswa', {{ $siswa->id_siswa }})" wire:confirm="Hapus siswa ini?">Hapus</button></td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada siswa yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->siswa->links() }}</div>
    </section>

    <section id="kelas" x-cloak x-show="activeSection === 'kelas'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span>Daftar Kelas</span><button type="button" class="btn btn-sm btn-app-primary" wire:click="openCreate('kelas')">Tambah Kelas</button></div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianKelas" class="form-control" placeholder="Cari nama kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama Kelas</th><th>Wali Kelas</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse ($this->kelas as $kelas)<tr wire:key="admin-kelas-{{ $kelas->id_kelas }}"><td>{{ $kelas->nama_kelas }}</td><td>{{ $kelas->wali_kelas ?? '-' }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEdit('kelas', {{ $kelas->id_kelas }})">Edit</button> <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete('kelas', {{ $kelas->id_kelas }})" wire:confirm="Hapus kelas ini?">Hapus</button></td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada kelas yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->kelas->links() }}</div>
    </section>

    <section id="jadwal" x-cloak x-show="activeSection === 'jadwal'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span>Jadwal Mengajar</span><button type="button" class="btn btn-sm btn-app-primary" wire:click="openCreate('jadwal')">Tambah Jadwal</button></div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianJadwal" class="form-control" placeholder="Cari guru, mata pelajaran, atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Hari</th><th>Jam</th><th>Guru</th><th>Mapel</th><th>Kelas</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse ($this->jadwalMengajarHariIni as $jadwal)<tr wire:key="admin-jadwal-{{ $jadwal->id_jadwal }}"><td>{{ $jadwal->hari }}</td><td>Ke-{{ $jadwal->jam_ke }} <span class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</span></td><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->nama_kelas }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEdit('jadwal', {{ $jadwal->id_jadwal }})">Edit</button> <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete('jadwal', {{ $jadwal->id_jadwal }})" wire:confirm="Hapus jadwal ini?">Hapus</button></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal mengajar yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jadwalMengajarHariIni->links() }}</div>
    </section>

    <section id="jadwal-piket" x-cloak x-show="activeSection === 'jadwal-piket'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span>Jadwal Piket</span><button type="button" class="btn btn-sm btn-app-primary" wire:click="openCreate('piket')">Tambah Jadwal Piket</button></div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianPiket" class="form-control" placeholder="Cari nama guru, status, atau keterangan piket..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Tanggal</th><th>Guru</th><th>Jam</th><th>Status</th><th>Keterangan</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse ($this->jadwalPiketHariIni as $piket)<tr wire:key="admin-piket-{{ $piket->id_jadwal_piket }}"><td>{{ \Illuminate\Support\Carbon::parse($piket->tanggal)->format('d/m/Y') }}</td><td>{{ $piket->nama }}</td><td>{{ substr($piket->jam_mulai, 0, 5) }}–{{ substr($piket->jam_selesai, 0, 5) }}</td><td>{{ $piket->status }}</td><td>{{ $piket->keterangan ?: '-' }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEdit('piket', {{ $piket->id_jadwal_piket }})">Edit</button> <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete('piket', {{ $piket->id_jadwal_piket }})" wire:confirm="Hapus jadwal piket ini?">Hapus</button></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal piket yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jadwalPiketHariIni->links() }}</div>
    </section>

    <section id="jurnal" x-cloak x-show="activeSection === 'jurnal'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Jurnal</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianJurnal" class="form-control" placeholder="Cari guru atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Tanggal</th><th>Guru</th><th>Kelas</th><th>Status</th></tr></thead><tbody>
            @forelse ($this->jurnal as $jurnal)<tr wire:key="admin-jurnal-{{ $jurnal->id_jurnal }}"><td>{{ optional($jurnal->tanggal)->format('d/m/Y') }}</td><td>{{ $jurnal->guru?->nama ?? '-' }}</td><td>{{ $jurnal->kelas?->nama_kelas ?? '-' }}</td><td><span class="badge {{ $jurnal->status_validasi === 'Divalidasi' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}</span></td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">Belum ada jurnal yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jurnal->links() }}</div>
    </section>

    <section id="dispensasi" x-cloak x-show="activeSection === 'dispensasi'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Dispensasi</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianDispensasi" class="form-control" placeholder="Cari siswa, kelas, atau status..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Keterangan</th><th>Status</th></tr></thead><tbody>
            @forelse ($this->dispensasi as $dispensasi)<tr wire:key="admin-dispensasi-{{ $dispensasi->id_dispensasi }}"><td>{{ \Illuminate\Support\Carbon::parse($dispensasi->tanggal)->format('d/m/Y') }}</td><td>{{ $dispensasi->nama_siswa ?? '-' }}</td><td>{{ $dispensasi->nama_kelas ?? '-' }}</td><td>{{ $dispensasi->jenis_dispensasi }}</td><td>{{ $dispensasi->alasan ?: '-' }}</td><td>{{ $dispensasi->status }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada dispensasi yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->dispensasi->links() }}</div>
    </section>

    @if ($showModal)
        <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3" style="z-index: 1060; background: rgba(15, 23, 42, .58);" wire:click.self="closeModal" wire:keydown.escape.window="closeModal">
            <section class="card-custom w-100 overflow-hidden" style="max-width: 720px; max-height: 90vh;" role="dialog" aria-modal="true" aria-labelledby="admin-form-title">
                <div class="card-header-custom d-flex align-items-center justify-content-between gap-2"><span id="admin-form-title">{{ $editingId ? 'Edit' : 'Tambah' }} {{ match($modalType) { 'pengguna' => 'Pengguna', 'siswa' => 'Siswa', 'kelas' => 'Kelas', 'jadwal' => 'Jadwal Mengajar', 'piket' => 'Jadwal Piket' } }}</span><button type="button" class="btn-close" aria-label="Tutup" wire:click="closeModal"></button></div>
                <form wire:submit="save" class="p-3 overflow-auto" style="max-height: calc(90vh - 62px);">
                    <div class="row g-3">
                        @if ($modalType === 'pengguna')
                            <div class="col-md-6"><label class="form-label">Nama</label><input wire:model="form.nama" class="form-control">@error('form.nama')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">NIP/ID</label><input wire:model="form.nip" class="form-control">@error('form.nip')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Role</label><select wire:model="form.role" class="form-select">@foreach (self::ROLE as $role)<option value="{{ $role }}">{{ ucfirst($role) }}</option>@endforeach</select>@error('form.role')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Mata Pelajaran</label><input wire:model="form.mapel_diampu" class="form-control">@error('form.mapel_diampu')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Status Kepegawaian</label><select wire:model="form.status_kepegawaian" class="form-select"><option value="">- Pilih -</option>@foreach (self::STATUS_KEPEGAWAIAN as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select>@error('form.status_kepegawaian')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">No. HP</label><input wire:model="form.no_hp" class="form-control">@error('form.no_hp')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Kelas</label><select wire:model="form.id_kelas" class="form-select"><option value="">- Tidak ada -</option>@foreach ($this->kelasOptions as $kelas)<option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>@endforeach</select>@error('form.id_kelas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">{{ $editingId ? 'Password Baru (opsional)' : 'Password' }}</label><input type="password" wire:model="form.password" class="form-control" autocomplete="new-password">@if ($editingId)<div class="form-text">Kosongkan untuk mempertahankan password saat ini.</div>@endif @error('form.password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                        @elseif ($modalType === 'siswa')
                            <div class="col-md-6"><label class="form-label">Nama Siswa</label><input wire:model="form.nama_siswa" class="form-control">@error('form.nama_siswa')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Kelas</label><select wire:model="form.id_kelas" class="form-select"><option value="">- Pilih kelas -</option>@foreach ($this->kelasOptions as $kelas)<option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>@endforeach</select>@error('form.id_kelas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                        @elseif ($modalType === 'kelas')
                            <div class="col-md-6"><label class="form-label">Nama Kelas</label><input wire:model="form.nama_kelas" class="form-control">@error('form.nama_kelas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Wali Kelas</label><input wire:model="form.wali_kelas" class="form-control">@error('form.wali_kelas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                        @elseif ($modalType === 'jadwal' || $modalType === 'piket')
                            <div class="col-md-6"><label class="form-label">Guru</label><select wire:model="form.id_guru" class="form-select"><option value="">- Pilih guru -</option>@foreach ($this->guruOptions as $guru)<option value="{{ $guru->id_pengguna }}">{{ $guru->nama }}</option>@endforeach</select>@error('form.id_guru')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            @if ($modalType === 'jadwal')
                                <div class="col-md-6"><label class="form-label">Kelas</label><select wire:model="form.id_kelas" class="form-select"><option value="">- Pilih kelas -</option>@foreach ($this->kelasOptions as $kelas)<option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>@endforeach</select>@error('form.id_kelas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label class="form-label">Hari</label><select wire:model="form.hari" class="form-select">@foreach (self::HARI as $hari)<option value="{{ $hari }}">{{ $hari }}</option>@endforeach</select>@error('form.hari')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label class="form-label">Jam ke</label><input type="number" min="1" wire:model="form.jam_ke" class="form-control">@error('form.jam_ke')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            @else
                                <div class="col-md-6"><label class="form-label">Tanggal</label><input type="date" wire:model="form.tanggal" class="form-control">@error('form.tanggal')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            @endif
                            <div class="col-md-6"><label class="form-label">Jam Mulai</label><input type="time" wire:model="form.jam_mulai" class="form-control">@error('form.jam_mulai')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label">Jam Selesai</label><input type="time" wire:model="form.jam_selesai" class="form-control">@error('form.jam_selesai')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            @if ($modalType === 'piket')
                                <div class="col-md-6"><label class="form-label">Status</label><select wire:model="form.status" class="form-select">@foreach (self::STATUS_PIKET as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select>@error('form.status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label class="form-label">Keterangan</label><textarea wire:model="form.keterangan" class="form-control" rows="3"></textarea>@error('form.keterangan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            @endif
                        @endif
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn btn-outline-secondary" wire:click="closeModal">Batal</button><button type="submit" class="btn btn-app-primary" wire:loading.attr="disabled">Simpan</button></div>
                </form>
            </section>
        </div>
    @endif
</div>
