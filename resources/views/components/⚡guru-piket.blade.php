<?php

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\KehadiranGuru;
use App\Services\KehadiranGuruService;
use App\Services\GuruPiketAccessService;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'per-kelas'; // 'per-kelas' | 'per-guru'
    public string $searchJadwal = '';
    public ?int $detailJadwalId = null;

    public function getBolehInputPiketProperty(): bool
    {
        return session('role') === 'guru'
            && app(GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'));
    }

    public function getStatusKehadiranGuruProperty(): \Illuminate\Support\Collection
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $hari = $sekarang->locale('id')->translatedFormat('l');
        $kehadiranGuru = app(KehadiranGuruService::class);

        return Jadwal::query()
            ->with('kelas')
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.hari', $hari)
            ->select(['jadwal.*', 'pengguna.nama as nama_guru', 'pengguna.mapel_diampu'])
            ->orderByDesc('jadwal.jam_ke') // Newest first
            ->orderBy('pengguna.nama')
            ->get()
            ->map(function (Jadwal $jadwal) use ($kehadiranGuru, $sekarang): Jadwal {
                $status = $kehadiranGuru->statusUntukJadwal($jadwal, $sekarang);

                // Check if guru has approved izin
                $hasIzin = class_exists(\App\Models\IzinGuru::class)
                    && \App\Models\IzinGuru::isGuruIzin($jadwal->id_guru, $sekarang->toDateString(), $jadwal->jam_ke);

                $jadwal->status_sistem = match ($status) {
                    'Hadir' => 'Hadir (jurnal diisi)',
                    'Izin' => 'Izin resmi',
                    'Sakit' => 'Sakit resmi',
                    KehadiranGuruService::STATUS_TANPA_KETERANGAN => 'Tidak hadir tanpa keterangan',
                    default => $hasIzin ? 'Izin disetujui' : 'Menunggu jam selesai',
                };

                $jadwal->has_izin = $hasIzin;

                return $jadwal;
            });
    }

    public function getJadwalTersaringProperty(): \Illuminate\Support\Collection
    {
        $search = mb_strtolower(trim($this->searchJadwal));

        if ($search === '') {
            return $this->statusKehadiranGuru;
        }

        return $this->statusKehadiranGuru->filter(function (Jadwal $jadwal) use ($search): bool {
            $kolomPencarian = [
                $jadwal->nama_guru,
                $jadwal->mapel_diampu,
                $jadwal->kelas?->nama_kelas,
            ];

            foreach ($kolomPencarian as $nilai) {
                if ($nilai !== null && mb_stripos((string) $nilai, $search) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    public function getBelumMengisiProperty()
    {
        return $this->statusKehadiranGuru->filter(
            fn(Jadwal $jadwal): bool => $jadwal->status_sistem === 'Tidak hadir tanpa keterangan'
        );
    }

    public function getJadwalPerKelasProperty(): array
    {
        return $this->jadwalTersaring
            ->groupBy('id_kelas')
            ->map(function ($group) {
                $kelas = $group->first()->kelas;
                $guruGroups = $group->groupBy('id_guru')->map(function ($guruGroup) {
                    $guru = $guruGroup->first();
                    return [
                        'id_guru' => $guru->id_guru,
                        'nama_guru' => $guru->nama_guru,
                        'mapel_diampu' => $guru->mapel_diampu,
                        'jadwal' => $guruGroup->sortBy('jam_ke')->values(),
                    ];
                })->sortBy('nama_guru')->values();

                return [
                    'id_kelas' => $kelas?->id_kelas,
                    'nama_kelas' => $kelas?->nama_kelas ?? '-',
                    'guru' => $guruGroups,
                    'total_jam' => $group->count(),
                    'terisi' => $group->where('status_sistem', 'Hadir (jurnal diisi)')->count(),
                    'izin' => $group->whereIn('status_sistem', ['Izin resmi', 'Sakit resmi', 'Izin disetujui'])->count(),
                    'belum_mengisi' => $group->where('status_sistem', 'Tidak hadir tanpa keterangan')->count(),
                ];
            })
            ->sortBy('nama_kelas')
            ->values()
            ->toArray();
    }

    public function getJadwalPerGuruProperty(): array
    {
        return $this->jadwalTersaring
            ->groupBy('id_guru')
            ->map(function ($group) {
                $guru = $group->first();
                $kelasGroups = $group->groupBy('id_kelas')->map(function ($kelasGroup) {
                    $kelas = $kelasGroup->first()->kelas;
                    return [
                        'id_kelas' => $kelas?->id_kelas,
                        'nama_kelas' => $kelas?->nama_kelas ?? '-',
                        'jadwal' => $kelasGroup->sortBy('jam_ke')->values(),
                    ];
                })->sortBy('nama_kelas')->values();

                return [
                    'id_guru' => $guru->id_guru,
                    'nama_guru' => $guru->nama_guru,
                    'mapel_diampu' => $guru->mapel_diampu,
                    'kelas' => $kelasGroups,
                    'total_jam' => $group->count(),
                    'terisi' => $group->where('status_sistem', 'Hadir (jurnal diisi)')->count(),
                    'izin' => $group->whereIn('status_sistem', ['Izin resmi', 'Sakit resmi', 'Izin disetujui'])->count(),
                    'belum_mengisi' => $group->where('status_sistem', 'Tidak hadir tanpa keterangan')->count(),
                ];
            })
            ->sortBy('nama_guru')
            ->values()
            ->toArray();
    }

    public function openDetail(int $idJadwal): void
    {
        $this->detailJadwalId = $idJadwal;
    }

    public function closeDetail(): void
    {
        $this->detailJadwalId = null;
    }

    public function getDetailJadwalProperty(): ?Jadwal
    {
        if ($this->detailJadwalId === null) {
            return null;
        }
        return $this->statusKehadiranGuru->firstWhere('id_jadwal', $this->detailJadwalId);
    }

    public function getBelumMengisiCountProperty(): int
    {
        return $this->belumMengisi->count();
    }

    public function getTotalJadwalCountProperty(): int
    {
        return $this->statusKehadiranGuru->count();
    }

    public function setTindakLanjut(int $idJadwal, string $aksi): void
    {
        abort_unless($this->bolehInputPiket, 403);
        abort_unless(in_array($aksi, ['hadir_lupa', 'tidak_hadir_tanpa_izin', 'tugas_titipan']), 422);

        $jadwal = Jadwal::findOrFail($idJadwal);
        $sekarang = Carbon::now('Asia/Jakarta');
        $tanggal = $sekarang->toDateString();

        $kehadiran = KehadiranGuru::where('id_jadwal', $idJadwal)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($aksi === 'hadir_lupa') {
            // Send reminder - deadline end of day
            if ($kehadiran) {
                $kehadiran->update([
                    'status' => 'Hadir (lupa isi jurnal)',
                    'sumber' => 'Piket',
                    'catatan' => 'Guru hadir tapi lupa isi jurnal. Deadline isi jurnal: hari ini jam 23:59.',
                ]);
            } else {
                KehadiranGuru::create([
                    'id_jadwal' => $idJadwal,
                    'tanggal' => $tanggal,
                    'id_guru' => $jadwal->id_guru,
                    'status' => 'Hadir (lupa isi jurnal)',
                    'sumber' => 'Piket',
                    'catatan' => 'Guru hadir tapi lupa isi jurnal. Deadline isi jurnal: hari ini jam 23:59.',
                ]);
            }
            session()->flash('success', 'Pengingat dikirim ke guru. Deadline isi jurnal: hari ini jam 23:59.');
        } elseif ($aksi === 'tidak_hadir_tanpa_izin') {
            // Mark as Tanpa Keterangan, assign substitute/give tasks
            if ($kehadiran) {
                $kehadiran->update([
                    'status' => KehadiranGuruService::STATUS_TANPA_KETERANGAN,
                    'sumber' => 'Piket',
                    'catatan' => 'Tidak hadir tanpa izin. Tugas titipan ditugaskan ke guru pengganti.',
                ]);
            } else {
                KehadiranGuru::create([
                    'id_jadwal' => $idJadwal,
                    'tanggal' => $tanggal,
                    'id_guru' => $jadwal->id_guru,
                    'status' => KehadiranGuruService::STATUS_TANPA_KETERANGAN,
                    'sumber' => 'Piket',
                    'catatan' => 'Tidak hadir tanpa izin. Tugas titipan ditugaskan ke guru pengganti.',
                ]);
            }
            session()->flash('success', 'Ditandai "Tidak hadir tanpa keterangan". Akan masuk rekap Wakasek.');
        } elseif ($aksi === 'tugas_titipan') {
            // Redirect to dispensasi for tugas titipan
            $this->dispatch('buka-dispensasi', kelasId: $jadwal->id_kelas);
        }

        $this->closeDetail();
    }

    public function endOfDayAuto(): void
    {
        // Auto mark "Tidak Mengisi" for end of day - goes to wakasek rekap
        $sekarang = Carbon::now('Asia/Jakarta');
        $hari = $sekarang->locale('id')->translatedFormat('l');
        $tanggal = $sekarang->toDateString();

        $jadwalBelumIsi = Jadwal::where('hari', $hari)
            ->whereDoesntHave('jurnalHariIni', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal)
                    ->where('adalah_pengajuan_izin', false);
            })
            ->get();

        foreach ($jadwalBelumIsi as $jadwal) {
            KehadiranGuru::updateOrCreate(
                ['id_jadwal' => $jadwal->id_jadwal, 'tanggal' => $tanggal],
                [
                    'id_guru' => $jadwal->id_guru,
                    'status' => 'Tidak Mengisi',
                    'sumber' => 'Sistem (Otomatis)',
                    'catatan' => 'Otomatis: jam selesai lewat tanpa jurnal/izin.',
                ]
            );
        }

        if ($jadwalBelumIsi->isNotEmpty()) {
            session()->flash('success', "Otomatis: {$jadwalBelumIsi->count()} jadwal ditandai 'Tidak Mengisi' untuk rekap Wakasek.");
        }
    }
};
?>

<div wire:poll.30s>

    {{-- HEADER HALAMAN --}}
    <div class="role-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="page-title">Piket &amp; Pemantauan Tugas</div>
            <div class="role-page-description">
                Pantau kehadiran guru otomatis berdasarkan jurnal dan koordinasikan penyerahan tugas titipan.
            </div>
        </div>

        {{-- AKSI PIKET --}}
        @if ($this->bolehInputPiket)
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('piket-kehadiran') }}" class="btn btn-outline-primary">
                ✓ Kehadiran Siswa
            </a>
            <a href="{{ route('dispensasi') }}" class="btn btn-app-primary">
                + Izin &amp; Dispensasi Siswa
            </a>
        </div>
        @endif
    </div>
    <div class="role-page-actions mb-3"></div>

    {{-- ALERT AKSES PIKET --}}
    @unless ($this->bolehInputPiket)
    <div class="alert alert-warning mb-4" role="status">
        Anda tidak memiliki hak akses piket hari ini.
    </div>
    @endunless

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="status">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
    @endif

    {{-- STATISTIK SUMMARY --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--danger);">{{ $this->belumMengisiCount }}</div>
                <div class="text-muted small">Belum mengisi jurnal setelah jam selesai</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--accent);">{{ $this->totalJadwalCount }}</div>
                <div class="text-muted small">Jadwal mengajar hari ini</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--warn);">
                    {{ $this->statusKehadiranGuru->where('status_sistem', 'Menunggu jam selesai')->count() }}
                </div>
                <div class="text-muted small">Masih menunggu jam selesai</div>
            </div>
        </div>
    </div>

    {{-- PERLU TINDAK LANJUT --}}
    @if ($this->belumMengisiCount > 0)
    <div class="card-custom mb-4 border border-danger">
        <div class="card-header-custom text-danger">Perlu Tindak Lanjut / Koordinasi Tugas</div>
        <div class="p-3 text-muted small">
            Hubungi guru terkait untuk memastikan penyampaian tugas atau kondisi kelas.
        </div>
        @foreach ($this->belumMengisi as $jadwal)
        <div class="p-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2"
            wire:key="terlambat-{{ $jadwal->id_jadwal }}">
            <div>
                <strong>{{ $jadwal->nama_guru }}</strong>
                <div class="text-muted small">
                    {{ $jadwal->mapel_diampu ?: '-' }} · {{ $jadwal->kelas?->nama_kelas ?: '-' }}
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-danger fw-semibold">
                    Jam ke-{{ $jadwal->jam_ke }} · belum ada jurnal
                </span>
                @if ($this->bolehInputPiket)
                <button type="button" class="btn btn-sm btn-outline-danger"
                    wire:click="setTindakLanjut({{ $jadwal->id_jadwal }}, 'hadir_lupa')"
                    wire:confirm="Kirim pengingat isi jurnal (deadline hari ini jam 23:59)?">
                    Kirim Pengingat
                </button>
                <button type="button" class="btn btn-sm btn-danger"
                    wire:click="setTindakLanjut({{ $jadwal->id_jadwal }}, 'tidak_hadir_tanpa_izin')"
                    wire:confirm="Tandai 'Tidak hadir tanpa keterangan'? Data masuk rekap Wakasek.">
                    Tandai Tanpa Keterangan
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- AKSI AKHIR HARI --}}
    @if ($this->bolehInputPiket)
    <div class="card-custom p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="fw-semibold">Proses Akhir Hari (Otomatis)</div>
            <div class="text-muted small">
                Tandai jadwal tanpa jurnal/izin sebagai "Tidak Mengisi" agar masuk rekap Wakasek.
            </div>
        </div>
        <button type="button" class="btn btn-outline-danger" wire:click="endOfDayAuto"
            wire:confirm="Jalankan proses akhir hari untuk semua jadwal yang belum terisi?">
            Proses Akhir Hari
        </button>
    </div>
    @endif

    {{-- PENCARIAN --}}
    <div class="card-custom p-3 mb-3">
        <input type="search" class="form-control" placeholder="Cari nama guru, mata pelajaran, atau kelas..."
            aria-label="Cari jadwal guru" wire:model.live.debounce.300ms="searchJadwal">
    </div>

    {{-- TAB PER KELAS / PER GURU --}}
    <div class="card-custom overflow-hidden">
        <div class="card-header-custom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="fw-bold">Status Jadwal &amp; Pemantauan Kelas</div>
                <div class="small fw-normal opacity-75">{{ $this->totalJadwalCount }} jadwal hari ini</div>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Pilih tampilan">
                <button type="button" @class(['btn', 'btn-primary' => $activeTab === 'per-kelas', 'btn-outline-secondary' => $activeTab !== 'per-kelas'])
                    wire:click="$set('activeTab', 'per-kelas')">Per Kelas</button>
                <button type="button" @class(['btn', 'btn-primary' => $activeTab === 'per-guru', 'btn-outline-secondary' => $activeTab !== 'per-guru'])
                    wire:click="$set('activeTab', 'per-guru')">Per Guru</button>
            </div>
        </div>

        {{-- TAB 1: PER KELAS --}}
        @if ($activeTab === 'per-kelas')
        <div class="p-3 d-grid gap-4">
            @forelse ($this->jadwalPerKelas as $kelasGroup)
            <div class="card border" wire:key="piket-kelas-{{ $kelasGroup['id_kelas'] }}">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="fw-bold">{{ $kelasGroup['nama_kelas'] }}</div>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-secondary">{{ $kelasGroup['total_jam'] }} jam</span>
                        <span class="badge bg-success">{{ $kelasGroup['terisi'] }} terisi</span>
                        @if ($kelasGroup['izin'] > 0)<span class="badge bg-warning text-dark">{{ $kelasGroup['izin'] }} izin</span>@endif
                        @if ($kelasGroup['belum_mengisi'] > 0)<span class="badge bg-danger">{{ $kelasGroup['belum_mengisi'] }} belum</span>@endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Guru / Mapel</th>
                                <th class="text-center">Jam</th>
                                <th class="text-center">Waktu</th>
                                <th class="text-center">Status Sistem</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($kelasGroup['guru'] as $guru)
                            @foreach ($guru['jadwal'] as $jadwal)
                            <tr wire:key="pikelas-jadwal-{{ $jadwal->id_jadwal }}">
                                <td>
                                    <strong>{{ $guru['nama_guru'] }}</strong>
                                    <div class="text-muted small">{{ $guru['mapel_diampu'] ?: '-' }}</div>
                                </td>
                                <td class="text-center">Ke-{{ $jadwal->jam_ke }}</td>
                                <td class="text-center text-muted small">
                                    {{ substr($jadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($jadwal->jam_selesai, 0, 5) }}
                                </td>
                                <td class="text-center">
                                    @php($badgeClass = match ($jadwal->status_sistem) {
                                        'Hadir (jurnal diisi)' => 'bg-success',
                                        'Tidak hadir tanpa keterangan' => 'bg-danger',
                                        'Izin resmi', 'Sakit resmi', 'Izin disetujui' => 'bg-warning text-dark',
                                        'Tidak Mengisi' => 'bg-danger',
                                        default => 'bg-secondary',
                                    })
                                    <span class="badge {{ $badgeClass }}">{{ $jadwal->status_sistem }}</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="openDetail({{ $jadwal->id_jadwal }})">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4">
                {{ trim($searchJadwal) !== '' ? 'Tidak ada jadwal yang cocok dengan pencarian.' : 'Tidak ada jadwal hari ini.' }}
            </div>
            @endforelse
        </div>
        @endif

        {{-- TAB 2: PER GURU --}}
        @if ($activeTab === 'per-guru')
        <div class="p-3 d-grid gap-4">
            @forelse ($this->jadwalPerGuru as $guruGroup)
            <div class="card border" wire:key="piket-guru-{{ $guruGroup['id_guru'] }}">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-bold">{{ $guruGroup['nama_guru'] }}</div>
                        <div class="small text-muted">{{ $guruGroup['mapel_diampu'] ?: '-' }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-secondary">{{ $guruGroup['total_jam'] }} jam</span>
                        <span class="badge bg-success">{{ $guruGroup['terisi'] }} terisi</span>
                        @if ($guruGroup['izin'] > 0)<span class="badge bg-warning text-dark">{{ $guruGroup['izin'] }} izin</span>@endif
                        @if ($guruGroup['belum_mengisi'] > 0)<span class="badge bg-danger">{{ $guruGroup['belum_mengisi'] }} belum</span>@endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kelas</th>
                                <th class="text-center">Jam</th>
                                <th class="text-center">Waktu</th>
                                <th class="text-center">Status Sistem</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($guruGroup['kelas'] as $kelasGroup)
                            @foreach ($kelasGroup['jadwal'] as $jadwal)
                            <tr wire:key="piketguru-jadwal-{{ $jadwal->id_jadwal }}">
                                <td>{{ $kelasGroup['nama_kelas'] }}</td>
                                <td class="text-center">Ke-{{ $jadwal->jam_ke }}</td>
                                <td class="text-center text-muted small">
                                    {{ substr($jadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($jadwal->jam_selesai, 0, 5) }}
                                </td>
                                <td class="text-center">
                                    @php($badgeClass = match ($jadwal->status_sistem) {
                                        'Hadir (jurnal diisi)' => 'bg-success',
                                        'Tidak hadir tanpa keterangan' => 'bg-danger',
                                        'Izin resmi', 'Sakit resmi', 'Izin disetujui' => 'bg-warning text-dark',
                                        'Tidak Mengisi' => 'bg-danger',
                                        default => 'bg-secondary',
                                    })
                                    <span class="badge {{ $badgeClass }}">{{ $jadwal->status_sistem }}</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="openDetail({{ $jadwal->id_jadwal }})">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4">
                {{ trim($searchJadwal) !== '' ? 'Tidak ada jadwal yang cocok dengan pencarian.' : 'Tidak ada jadwal hari ini.' }}
            </div>
            @endforelse
        </div>
        @endif
    </div>

    {{-- DETAIL JADWAL (halaman detail, bukan modal) --}}
    @if ($this->detailJadwal)
    <x-layouts.detail-page
        title="Detail Jadwal Piket"
        :subtitle="$this->detailJadwal->nama_guru.' · '.($this->detailJadwal->kelas?->nama_kelas ?? '-')"
        backRoute="guru-piket"
    >
        <x-slot:actions>
            @if ($this->bolehInputPiket)
            <a href="{{ route('dispensasi') }}" class="btn btn-sm btn-app-primary">Buka Dispensasi</a>
            @endif
        </x-slot:actions>

        <div class="card-custom mb-4">
            <div class="card-header-custom">Informasi Jadwal</div>
            <div class="p-4">
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted">Guru</dt>
                    <dd class="col-sm-9">{{ $this->detailJadwal->nama_guru }}</dd>

                    <dt class="col-sm-3 text-muted">Mata Pelajaran</dt>
                    <dd class="col-sm-9">{{ $this->detailJadwal->mapel_diampu ?: '-' }}</dd>

                    <dt class="col-sm-3 text-muted">Kelas</dt>
                    <dd class="col-sm-9">{{ $this->detailJadwal->kelas?->nama_kelas ?: '-' }}</dd>

                    <dt class="col-sm-3 text-muted">Jam Ke</dt>
                    <dd class="col-sm-9">Ke-{{ $this->detailJadwal->jam_ke }}</dd>

                    <dt class="col-sm-3 text-muted">Waktu</dt>
                    <dd class="col-sm-9">
                        {{ substr($this->detailJadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($this->detailJadwal->jam_selesai, 0, 5) }}
                    </dd>

                    <dt class="col-sm-3 text-muted">Status Sistem</dt>
                    <dd class="col-sm-9">
                        <span class="badge {{ match ($this->detailJadwal->status_sistem) {
                            'Hadir (jurnal diisi)' => 'bg-success',
                            'Tidak hadir tanpa keterangan' => 'bg-danger',
                            'Izin resmi', 'Sakit resmi', 'Izin disetujui' => 'bg-warning text-dark',
                            default => 'bg-secondary',
                        } }}">{{ $this->detailJadwal->status_sistem }}</span>
                    </dd>
                </dl>
            </div>
        </div>

        @if ($this->bolehInputPiket)
        <div class="card-custom">
            <div class="card-header-custom">Tindak Lanjut Piket</div>
            <div class="p-4 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-warning"
                    wire:click="setTindakLanjut({{ $this->detailJadwal->id_jadwal }}, 'hadir_lupa')"
                    wire:confirm="Kirim pengingat isi jurnal (deadline hari ini jam 23:59)?">
                    Hadir tapi Lupa Isi → Kirim Pengingat
                </button>
                <button type="button" class="btn btn-danger"
                    wire:click="setTindakLanjut({{ $this->detailJadwal->id_jadwal }}, 'tidak_hadir_tanpa_izin')"
                    wire:confirm="Tandai 'Tidak hadir tanpa keterangan'? Data masuk rekap Wakasek.">
                    Tidak Hadir Tanpa Izin → Tandai Tanpa Keterangan
                </button>
                <button type="button" class="btn btn-outline-secondary"
                    wire:click="setTindakLanjut({{ $this->detailJadwal->id_jadwal }}, 'tugas_titipan')">
                    Beri Tugas Titipan / Guru Pengganti
                </button>
            </div>
            <div class="p-3 border-top text-muted small">
                Kirim pengingat berlaku sampai akhir hari. Jadwal tanpa jurnal/izin diproses otomatis menjadi "Tidak Mengisi"
                pada akhir hari dan masuk ke rekap Wakasek.
            </div>
        </div>
        @endif
    </x-layouts.detail-page>
    @endif
</div>