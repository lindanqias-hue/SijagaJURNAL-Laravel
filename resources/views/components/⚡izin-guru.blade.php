<?php

use App\Models\IzinGuru;
use App\Models\Jadwal;
use App\Models\Kelas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $jenisIzin = 'Izin';
    public string $tanggal;
    public string $id_kelas = '';
    public int $jam_ke_mulai = 1;
    public int $jam_ke_selesai = 1;

    /** @var array<int, string> */
    public array $tugasPerJam = [];

    public string $catatan = '';

    public function mount(): void
    {
        abort_unless(session('role') === 'guru', 403);

        if (! session('id_pengguna')) {
            $this->redirectRoute('login');

            return;
        }

        $this->tanggal = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $this->muatKelasPertama();
        $this->generateTugasTemplate();
    }

    public function getJadwalKelasProperty()
    {
        if (! $this->id_kelas) {
            return collect();
        }

        return Jadwal::query()
            ->with('kelas')
            ->where('id_guru', session('id_pengguna'))
            ->where('id_kelas', $this->id_kelas)
            ->where('hari', $this->namaHari())
            ->orderBy('jam_ke')
            ->get();
    }

    public function getKelasListProperty()
    {
        $hari = $this->namaHari();

        if (! $hari || ! session('id_pengguna')) {
            return collect();
        }

        $idKelas = Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('hari', $hari)
            ->pluck('id_kelas')
            ->unique();

        return Kelas::query()
            ->whereIn('id_kelas', $idKelas)
            ->orderBy('nama_kelas')
            ->get();
    }

    public function getRiwayatProperty()
    {
        return IzinGuru::with('kelas')
            ->where('id_guru', session('id_pengguna'))
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    public function updatedTanggal(): void
    {
        $this->muatKelasPertama();
        $this->jam_ke_mulai = 1;
        $this->jam_ke_selesai = 1;
        $this->generateTugasTemplate();
    }

    public function updatedIdKelas(): void
    {
        $jadwal = $this->jadwalKelas;

        $this->jam_ke_mulai = (int) ($jadwal->first()?->jam_ke ?? 1);
        $this->jam_ke_selesai = (int) ($jadwal->last()?->jam_ke ?? 1);
        $this->generateTugasTemplate();
    }

    public function updatedJamKeMulai(): void
    {
        if ($this->jam_ke_selesai < $this->jam_ke_mulai) {
            $this->jam_ke_selesai = $this->jam_ke_mulai;
        }

        $this->generateTugasTemplate();
    }

    public function updatedJamKeSelesai(): void
    {
        $this->generateTugasTemplate();
    }

    public function setSetengahHari(): void
    {
        $jadwal = $this->jadwalKelas;

        if ($jadwal->isEmpty()) {
            return;
        }

        $jam = $jadwal->pluck('jam_ke')->map(fn ($nilai): int => (int) $nilai)->values();

        $tengah = (int) ceil($jam->count() / 2);

        $this->jam_ke_mulai = (int) $jam->first();
        $this->jam_ke_selesai = (int) $jam->get($tengah - 1);
        $this->generateTugasTemplate();
    }

    public function setSehariPenuh(): void
    {
        $jadwal = $this->jadwalKelas;

        if ($jadwal->isEmpty()) {
            return;
        }

        $this->jam_ke_mulai = (int) $jadwal->first()->jam_ke;
        $this->jam_ke_selesai = (int) $jadwal->last()->jam_ke;
        $this->generateTugasTemplate();
    }

    private function generateTugasTemplate(): void
    {
        $baris = [];

        for ($jam = $this->jam_ke_mulai; $jam <= $this->jam_ke_selesai; $jam++) {
            $baris[$jam] = $this->tugasPerJam[$jam] ?? '';
        }

        $this->tugasPerJam = $baris;
    }

    public function simpan(): void
    {
        $this->validate([
            'jenisIzin' => 'required|in:'.implode(',', IzinGuru::JENIS_IZIN),
            'tanggal' => 'required|date|after_or_equal:today',
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'jam_ke_mulai' => 'required|integer|min:1',
            'jam_ke_selesai' => 'required|integer|gte:jam_ke_mulai',
            'tugasPerJam.*' => 'nullable|string|max:1000',
            'catatan' => 'nullable|string|max:1000',
        ], [
            'id_kelas.required' => 'Kelas wajib dipilih.',
            'tanggal.after_or_equal' => 'Tanggal izin tidak boleh mendahului hari ini.',
            'jam_ke_selesai.gte' => 'Jam selesai harus sama atau setelah jam mulai.',
        ]);

        $jamTersedia = $this->jadwalKelas
            ->pluck('jam_ke')
            ->map(fn ($nilai): int => (int) $nilai);

        if (
            ! $jamTersedia->contains((int) $this->jam_ke_mulai)
            || ! $jamTersedia->contains((int) $this->jam_ke_selesai)
            || (int) $this->jam_ke_selesai < (int) $this->jam_ke_mulai
        ) {
            $this->addError('jam_ke_mulai', 'Jam yang dipilih tidak ada pada jadwal mengajar Anda untuk kelas dan tanggal ini.');

            return;
        }

        $tugas = collect($this->tugasPerJam)
            ->mapWithKeys(fn ($nilai, $jam): array => [(int) $jam => trim((string) $nilai)])
            ->filter(fn (string $nilai): bool => $nilai !== '')
            ->all();

        DB::transaction(function () use ($tugas): void {
            IzinGuru::create([
                'id_guru' => session('id_pengguna'),
                'id_kelas' => (int) $this->id_kelas,
                'tanggal' => $this->tanggal,
                'jam_ke_mulai' => $this->jam_ke_mulai,
                'jam_ke_selesai' => $this->jam_ke_selesai,
                'jenis_izin' => $this->jenisIzin,
                'tugas_per_jam' => $tugas,
                'status' => 'Menunggu',
                'catatan_validasi' => $this->catatan ?: null,
            ]);
        });

        session()->flash('success', 'Pengajuan izin berhasil dikirim ke Wakasek.');

        $this->reset(['tugasPerJam', 'catatan', 'id_kelas']);
        $this->jam_ke_mulai = 1;
        $this->jam_ke_selesai = 1;
        $this->muatKelasPertama();
        $this->generateTugasTemplate();
    }

    private function namaHari(): ?string
    {
        if (! $this->tanggal) {
            return null;
        }

        $hari = Carbon::parse($this->tanggal, 'Asia/Jakarta')->format('l');

        return [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ][$hari] ?? null;
    }

    private function muatKelasPertama(): void
    {
        $this->id_kelas = (string) ($this->kelasList->first()?->id_kelas ?? '');

        if ($this->id_kelas !== '') {
            $jadwal = $this->jadwalKelas;
            $this->jam_ke_mulai = (int) ($jadwal->first()?->jam_ke ?? 1);
            $this->jam_ke_selesai = (int) ($jadwal->last()?->jam_ke ?? 1);
        }
    }
};
?>

<div>
    {{-- HEADER --}}
    <div class="role-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="page-title">Pengajuan Izin Guru</div>
            <div class="role-page-description">
                Ajukan izin, sakit, dinas luar kota, atau cuti beserta tugas per jam. Disetujui oleh Wakasek.
            </div>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">&larr; Kembali</a>
    </div>
    <div class="role-page-actions mb-3"></div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="status">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
    @endif

    {{-- FORM --}}
    <div class="card-custom mb-4">
        <div class="card-header-custom">Form Pengajuan Izin</div>
        <div class="p-4">
            <form wire:submit="simpan">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="izin-jenis">Jenis Izin</label>
                        <select id="izin-jenis" class="form-select" wire:model.live="jenisIzin">
                            @foreach (\App\Models\IzinGuru::JENIS_IZIN as $jenis)
                            <option value="{{ $jenis }}">{{ $jenis }}</option>
                            @endforeach
                        </select>
                        @error('jenisIzin') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="izin-tanggal">Tanggal</label>
                        <input id="izin-tanggal" type="date" class="form-control" wire:model.live="tanggal"
                            min="{{ Carbon::now('Asia/Jakarta')->format('Y-m-d') }}">
                        @error('tanggal') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="izin-kelas">Kelas</label>
                        <select id="izin-kelas" class="form-select" wire:model.live="id_kelas">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($this->kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                            @endforeach
                        </select>
                        @error('id_kelas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    @if ($id_kelas)
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="izin-jam-mulai">Jam Mulai</label>
                        <select id="izin-jam-mulai" class="form-select" wire:model.live="jam_ke_mulai">
                            @foreach ($this->jadwalKelas as $jadwal)
                            <option value="{{ $jadwal->jam_ke }}">
                                Jam ke-{{ $jadwal->jam_ke }}
                                ({{ substr($jadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($jadwal->jam_selesai, 0, 5) }})
                            </option>
                            @endforeach
                        </select>
                        @error('jam_ke_mulai') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="izin-jam-selesai">Jam Selesai</label>
                        <select id="izin-jam-selesai" class="form-select" wire:model.live="jam_ke_selesai">
                            @foreach ($this->jadwalKelas as $jadwal)
                            @if ($jadwal->jam_ke >= $jam_ke_mulai)
                            <option value="{{ $jadwal->jam_ke }}">
                                Jam ke-{{ $jadwal->jam_ke }}
                                ({{ substr($jadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($jadwal->jam_selesai, 0, 5) }})
                            </option>
                            @endif
                            @endforeach
                        </select>
                        @error('jam_ke_selesai') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setSetengahHari">
                                Setengah Hari
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="setSehariPenuh">
                                Sehari Penuh
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- TUGAS PER JAM --}}
                    @if (count($tugasPerJam))
                    <div class="col-12">
                        <label class="form-label fw-semibold d-block mb-2">
                            Tugas per Jam
                            <small class="text-muted fw-normal">(kosongkan jika tidak ada tugas)</small>
                        </label>
                        <div class="row g-2">
                            @foreach ($tugasPerJam as $jam => $tugas)
                            <div class="col-12 col-md-6" wire:key="tugas-jam-{{ $jam }}">
                                <label class="form-label small text-muted" for="tugas-{{ $jam }}">Jam ke-{{ $jam }}</label>
                                <textarea id="tugas-{{ $jam }}" class="form-control form-control-sm" rows="2"
                                    wire:model.blur="tugasPerJam.{{ $jam }}"
                                    placeholder="Tugas untuk jam ke-{{ $jam }}"></textarea>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="izin-catatan">Catatan</label>
                        <textarea id="izin-catatan" class="form-control" rows="3" wire:model="catatan"
                            placeholder="Catatan tambahan (opsional)"></textarea>
                        @error('catatan') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-app-primary px-4" wire:loading.attr="disabled" wire:target="simpan">
                            <span wire:loading.remove wire:target="simpan">Kirim Pengajuan</span>
                            <span wire:loading wire:target="simpan">Mengirim…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- RIWAYAT --}}
    <div class="card-custom">
        <div class="card-header-custom">Riwayat Pengajuan Izin</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Kelas</th>
                        <th class="text-center">Jam</th>
                        <th class="text-center">Status</th>
                        <th>Catatan Wakasek</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->riwayat as $izin)
                    <tr wire:key="izin-guru-{{ $izin->id_izin }}">
                        <td>{{ $izin->tanggal?->format('d/m/Y') }}</td>
                        <td>{{ $izin->jenis_izin }}</td>
                        <td>{{ $izin->kelas?->nama_kelas ?? '-' }}</td>
                        <td class="text-center">Jam {{ $izin->jam_ke_mulai }}&ndash;{{ $izin->jam_ke_selesai }}</td>
                        <td class="text-center">
                            <span @class(['badge',
                                'bg-success' => $izin->status === 'Disetujui',
                                'bg-danger' => $izin->status === 'Ditolak',
                                'bg-warning text-dark' => $izin->status === 'Menunggu',
                            ])>
                                {{ $izin->status }}
                            </span>
                        </td>
                        <td>{{ $izin->catatan_validasi ?: '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan izin.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>