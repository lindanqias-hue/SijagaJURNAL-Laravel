<?php

use App\Models\AbsensiSiswa;
use App\Models\Jurnal;
use Livewire\Component;

new class extends Component
{
    public Jurnal $jurnal;

    public function mount(int $id): void
    {
        $query = Jurnal::query()
            ->with(['guru', 'kelas']);

        if (session('role') === 'guru') {
            $query->where('id_guru', session('id_pengguna'));
        } elseif (session('role') === 'sekretaris') {
            $idKelas = session('id_kelas');

            if (!$idKelas) {
                abort(404);
            }

            $query->where('id_kelas', $idKelas);
        }

        // Wakasek mengikuti riwayat dan monitoringnya: dapat melihat semua jurnal.

        $this->jurnal = $query->findOrFail($id);
    }

    public function getAbsensiSiswaProperty()
    {
        return AbsensiSiswa::query()
            ->with(['siswa', 'keteranganSiswa'])
            ->where('id_jurnal', $this->jurnal->id_jurnal)
            ->orderBy('id_siswa')
            ->get();
    }
};
?>

<x-halaman-detail
    judul="Detail Jurnal"
    subjudul="{{ $jurnal->kelas?->nama_kelas ?? '-' }} · {{ $jurnal->tanggal?->format('d M Y') }} · Jam ke-{{ $jurnal->jam_ke }}"
    eyebrow="Riwayat Jurnal"
    routeKembali="riwayat"
>
    <div class="row g-3 mb-4">
        @unless (session('role') === 'guru')
            <div class="col-md-6"><strong>Guru</strong><div>{{ $jurnal->guru?->nama ?? '-' }}</div></div>
        @endunless
        <div class="col-md-6"><strong>Kelas</strong><div>{{ $jurnal->kelas?->nama_kelas ?? '-' }}</div></div>
        <div class="col-md-6"><strong>Status guru</strong><div>{{ $jurnal->status_kehadiran_guru ?? '-' }}</div></div>
        <div class="col-md-6"><strong>Status validasi</strong><div>{{ $jurnal->status_validasi ?? '-' }}</div></div>
        <div class="col-12"><strong>Materi</strong><div class="mt-1">{{ $jurnal->materi ?: '-' }}</div></div>
        <div class="col-12"><strong>Catatan</strong><div class="mt-1">{{ $jurnal->catatan ?: '-' }}</div></div>
        <div class="col-12"><strong>Rekap kehadiran</strong><div>{{ $jurnal->jumlah_hadir }} hadir · {{ $jurnal->jumlah_tidak_hadir }} tidak hadir</div></div>
    </div>

    <h2 class="h5 mb-3">Absensi siswa</h2>
    @if ($this->absensiSiswa->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Siswa</th><th>Status</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @foreach ($this->absensiSiswa as $absensi)
                        <tr wire:key="jurnal-detail-absensi-{{ $absensi->id_absensi }}">
                            <td>{{ $absensi->siswa?->nama_siswa ?? '-' }}</td>
                            <td>{{ $absensi->keterangan }}</td>
                            <td>{{ $absensi->keteranganSiswa?->keterangan ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-muted mb-0">Data absensi siswa tidak tersedia untuk jurnal ini.</p>
    @endif
</x-halaman-detail>
