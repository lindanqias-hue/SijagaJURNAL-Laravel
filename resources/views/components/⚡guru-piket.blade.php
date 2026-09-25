<?php

use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Services\KehadiranGuruService;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public bool $showDaftarJadwal = false;

    public string $searchJadwal = '';

    public function mount(): void
    {
        $bertugasPiket = JadwalPiket::query()
            ->where('id_guru', session('id_pengguna'))
            ->whereDate('tanggal', Carbon::now('Asia/Jakarta')->toDateString())
            ->where('status', 'Aktif')
            ->exists();

        if (! $bertugasPiket) {
            abort(403, 'Halaman ini khusus untuk guru yang bertugas piket.');
        }
    }

    public function getStatusKehadiranGuruProperty()
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $hari = $sekarang->locale('id')->translatedFormat('l');
        $kehadiranGuru = app(KehadiranGuruService::class);

        return Jadwal::query()
            ->with('kelas')
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.hari', $hari)
            ->select(['jadwal.*', 'pengguna.nama as nama_guru', 'pengguna.mapel_diampu'])
            ->orderBy('pengguna.nama')
            ->orderBy('jadwal.jam_ke')
            ->get()
            ->map(function (Jadwal $jadwal) use ($kehadiranGuru, $sekarang): Jadwal {
                $status = $kehadiranGuru->statusUntukJadwal($jadwal, $sekarang);

                $jadwal->status_sistem = match ($status) {
                    'Hadir' => 'Hadir (jurnal diisi)',
                    'Izin' => 'Izin resmi',
                    'Sakit' => 'Sakit resmi',
                    KehadiranGuruService::STATUS_TANPA_KETERANGAN => 'Tidak hadir tanpa keterangan',
                    default => 'Menunggu jam selesai',
                };

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

    public function openDaftarJadwal(): void
    {
        $this->searchJadwal = '';
        $this->showDaftarJadwal = true;
    }

    public function closeDaftarJadwal(): void
    {
        $this->showDaftarJadwal = false;
        $this->searchJadwal = '';
    }
};
?>

<div wire:poll.30s>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="page-title">Piket & Pemantauan Tugas</div>
            <div class="text-muted mt-1" style="font-size:13px;">Pantau kehadiran guru otomatis berdasarkan jurnal dan
                koordinasikan penyerahan tugas titipan.</div>
        </div>
        <a href="{{ route('dispensasi') }}" class="btn btn-app-primary">+ Izin & Dispensasi Siswa</a>
    </div>

    <div class="alert alert-info mb-4">Guru piket fokus mendistribusikan tugas titipan/izin luar kelas. Validasi
        kehadiran guru berjalan otomatis oleh sistem melalui jurnal.</div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--danger);">{{ $this->belumMengisi->count() }}</div>
                <div class="text-muted small">Belum mengisi jurnal setelah jam selesai</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--accent);">{{ $this->statusKehadiranGuru->count() }}</div>
                <div class="text-muted small">Jadwal mengajar hari ini</div>
            </div>
        </div>
    </div>

    @if ($this->belumMengisi->isNotEmpty())
    <div class="card-custom mb-4 border border-danger">
        <div class="card-header-custom text-danger">Perlu Tindak Lanjut / Koordinasi Tugas</div>
        <div class="p-3 text-muted small">Hubungi guru terkait untuk memastikan penyampaian tugas atau kondisi kelas.
        </div>
        @foreach ($this->belumMengisi as $jadwal)
        <div class="p-3 border-top d-flex flex-wrap justify-content-between gap-2"
            wire:key="terlambat-{{ $jadwal->id_jadwal }}">
            <div><strong>{{ $jadwal->nama_guru }}</strong>
                <div class="text-muted small">{{ $jadwal->mapel_diampu ?: '-' }} ·
                    {{ $jadwal->kelas?->nama_kelas ?: '-' }}
                </div>
            </div>
            <div class="text-danger fw-semibold">Jam ke-{{ $jadwal->jam_ke }} · belum ada jurnal</div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="card-custom p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="fw-semibold">Status Jadwal & Pemantauan Kelas</div>
            <div class="text-muted small">{{ $this->statusKehadiranGuru->count() }} jadwal hari ini · nama guru urut A–Z</div>
        </div>
        <button type="button" class="btn btn-app-primary" wire:click="openDaftarJadwal">
            Lihat Jadwal Hari Ini
        </button>
    </div>

    @if ($showDaftarJadwal)
    <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3"
        style="z-index: 1060; background: rgba(15, 23, 42, .58);"
        wire:click.self="closeDaftarJadwal" wire:keydown.escape.window="closeDaftarJadwal">
        <section class="card-custom w-100 overflow-hidden" style="max-width: 1000px; max-height: 90vh;"
            role="dialog" aria-modal="true" aria-labelledby="piket-jadwal-title">
            <div class="card-header-custom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <div id="piket-jadwal-title">Status Jadwal & Pemantauan Kelas</div>
                    <div class="small fw-normal opacity-75">Nama guru diurutkan A–Z</div>
                </div>
                <button type="button" class="btn-close" aria-label="Tutup" wire:click="closeDaftarJadwal"></button>
            </div>
            <div class="p-3 border-bottom">
                <input type="search" class="form-control" placeholder="Cari nama guru, mata pelajaran, atau kelas..."
                    aria-label="Cari jadwal guru" wire:model.live.debounce.300ms="searchJadwal">
            </div>
            <div class="table-responsive" style="max-height: calc(90vh - 150px); overflow-y: auto;">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Guru / Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Jam</th>
                            <th>Status Sistem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->jadwalTersaring as $jadwal)
                        <tr wire:key="jadwal-{{ $jadwal->id_jadwal }}">
                            <td><strong>{{ $jadwal->nama_guru }}</strong>
                                <div class="text-muted small">{{ $jadwal->mapel_diampu ?: '-' }}</div>
                            </td>
                            <td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                            <td>Ke-{{ $jadwal->jam_ke }}
                                <div class="text-muted small">
                                    {{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}
                                </div>
                            </td>
                            <td>
                                <span @class([
                                    'badge',
                                    'bg-success' => $jadwal->status_sistem === 'Hadir (jurnal diisi)',
                                    'bg-danger' => $jadwal->status_sistem === 'Tidak hadir tanpa keterangan',
                                    'bg-warning text-dark' => in_array($jadwal->status_sistem, ['Izin resmi', 'Sakit resmi'], true),
                                    'bg-secondary' => $jadwal->status_sistem === 'Menunggu jam selesai',
                                ])>{{ $jadwal->status_sistem }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                {{ trim($searchJadwal) !== '' ? 'Tidak ada jadwal yang cocok dengan pencarian.' : 'Tidak ada jadwal hari ini.' }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @endif
</div>
