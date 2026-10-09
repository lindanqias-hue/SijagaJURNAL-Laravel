<?php

use App\Models\AbsensiSiswa;
use App\Models\Jurnal;
use Livewire\Component;

new class extends Component
{
    public Jurnal $jurnal;

    public function mount(int $id): void
    {
        $query = Jurnal::query()->with(['guru', 'kelas'])->whereKey($id);

        $role = session('role');

        if (in_array($role, ['guru', 'guru_piket'], true)) {
            $query->where('id_guru', session('id_pengguna'));
        }

        if ($role === 'sekretaris') {
            $idKelas = session('id_kelas');

            if (! $idKelas) {
                abort(403);
            }

            $query->where('id_kelas', $idKelas);
        }

        abort_unless(in_array($role, ['guru', 'guru_piket', 'wakasek', 'sekretaris', 'admin'], true), 403);

        $this->jurnal = $query->firstOrFail();
    }

    public function getAbsensiProperty()
    {
        return AbsensiSiswa::with(['siswa', 'keteranganSiswa'])
            ->where('id_jurnal', $this->jurnal->id_jurnal)
            ->get()
            ->sortBy(fn (AbsensiSiswa $absen): string => (string) $absen->siswa?->nama_siswa);
    }
};
?>

<x-layouts.detail-page
    :title="$jurnal->materi ?: 'Detail Jurnal'"
    :subtitle="$jurnal->guru?->nama.' · '.($jurnal->kelas?->nama_kelas ?? '-').' · '.\Carbon\Carbon::parse($jurnal->tanggal)->locale('id')->translatedFormat('l, d F Y')"
    backRoute="riwayat"
>
    <x-slot:actions>
        @if (session('role') === 'guru' && $jurnal->status_validasi === 'Menunggu')
        <a href="{{ route('input-jurnal') }}?edit={{ $jurnal->id_jurnal }}" class="btn btn-sm btn-app-primary">Edit Jurnal</a>
        @endif
    </x-slot:actions>

    <div class="row g-4">
        {{-- INFORMASI JURNAL --}}
        <div class="col-12 col-lg-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Informasi Jurnal</div>
                <div class="p-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Guru</dt>
                        <dd class="col-sm-8">{{ $jurnal->guru?->nama ?? '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Mata Pelajaran</dt>
                        <dd class="col-sm-8">{{ $jurnal->guru?->mapel_diampu ?: '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Kelas</dt>
                        <dd class="col-sm-8">{{ $jurnal->kelas?->nama_kelas ?? '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Tanggal</dt>
                        <dd class="col-sm-8">{{ \Carbon\Carbon::parse($jurnal->tanggal)->locale('id')->translatedFormat('d F Y') }}</dd>

                        <dt class="col-sm-4 text-muted">Jam Ke</dt>
                        <dd class="col-sm-8">Jam {{ $jurnal->jam_ke }}</dd>

                        <dt class="col-sm-4 text-muted">Kehadiran Guru</dt>
                        <dd class="col-sm-8">
                            <span @class(['badge',
                                'bg-success' => $jurnal->status_kehadiran_guru === 'Hadir',
                                'bg-warning text-dark' => in_array($jurnal->status_kehadiran_guru, ['Izin', 'Sakit'], true),
                                'bg-danger' => $jurnal->status_kehadiran_guru === 'Tanpa Keterangan',
                                'bg-secondary' => ! in_array($jurnal->status_kehadiran_guru, ['Hadir', 'Izin', 'Sakit', 'Tanpa Keterangan'], true),
                            ])>
                                {{ $jurnal->status_kehadiran_guru }}
                            </span>
                        </dd>

                        <dt class="col-sm-4 text-muted">Validasi</dt>
                        <dd class="col-sm-8">
                            <span @class(['badge',
                                'bg-success' => $jurnal->status_validasi === 'Divalidasi',
                                'bg-danger' => $jurnal->status_validasi === 'Ditolak',
                                'bg-warning text-dark' => $jurnal->status_validasi === 'Menunggu',
                            ])>
                                {{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}
                            </span>
                        </dd>

                        @if (in_array(session('role'), ['sekretaris', 'wakasek', 'admin'], true))
                        <dt class="col-sm-4 text-muted">Konfirmasi Sekretaris</dt>
                        <dd class="col-sm-8">{{ $jurnal->status_konfirmasi_sekretaris }}</dd>
                        @endif

                        <dt class="col-sm-4 text-muted">Jumlah Hadir</dt>
                        <dd class="col-sm-8">{{ $jurnal->jumlah_hadir }} dari {{ $this->absensi->count() }} siswa</dd>

                        <dt class="col-sm-4 text-muted">Tidak Hadir</dt>
                        <dd class="col-sm-8">{{ $jurnal->jumlah_tidak_hadir }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- CATATAN --}}
        <div class="col-12 col-lg-6">
            <div class="card-custom mb-4">
                <div class="card-header-custom">Materi</div>
                <div class="p-4">{{ $jurnal->materi }}</div>
            </div>

            @if ($jurnal->catatan)
            <div class="card-custom mb-4">
                <div class="card-header-custom">Catatan Guru</div>
                <div class="p-4">{{ $jurnal->catatan }}</div>
            </div>
            @endif

            @if ($jurnal->catatan_validasi)
            <div class="card-custom">
                <div class="card-header-custom text-warning">Catatan Validasi</div>
                <div class="p-4">{{ $jurnal->catatan_validasi }}</div>
            </div>
            @endif
        </div>

        {{-- ABSENSI SISWA --}}
        <div class="col-12">
            <div class="card-custom">
                <div class="card-header-custom">Absensi Siswa</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Siswa</th>
                                <th class="text-center">Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->absensi as $absen)
                            <tr wire:key="jurnal-detail-absen-{{ $absen->id_absensi }}">
                                <td>{{ $absen->siswa?->nama_siswa ?? '-' }}</td>
                                <td class="text-center">
                                    <span @class(['badge',
                                        'bg-success' => $absen->keterangan === 'Hadir',
                                        'bg-warning text-dark' => in_array($absen->keterangan, ['Izin', 'Sakit', 'Dispensasi'], true),
                                        'bg-danger' => in_array($absen->keterangan, ['Alpa', 'Tanpa Keterangan'], true),
                                    ])>
                                        {{ $absen->keterangan }}
                                    </span>
                                </td>
                                <td>{{ $absen->keteranganSiswa?->keterangan ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Belum ada data absensi untuk jurnal ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.detail-page>