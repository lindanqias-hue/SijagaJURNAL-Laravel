<?php

use App\Models\Dispensasi;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public Dispensasi $dispensasi;

    public function mount(int $id): void
    {
        abort_unless(
            in_array(session('role'), ['guru', 'wakasek', 'sekretaris', 'admin'], true),
            403
        );

        $dispensasi = Dispensasi::query()
            ->with([
                'siswa',
                'kelas',
                'guruPiket',
                'wakasek',
                'guruPengajar',
                'jadwal',
            ])
            ->findOrFail($id);

        /*
         * Sekretaris hanya boleh melihat dispensasi kelasnya.
         */
        if (session('role') === 'sekretaris') {
            abort_unless((int) $dispensasi->id_kelas === (int) session('id_kelas'), 403);
        }

        /*
         * Guru pengajar hanya boleh melihat dispensasi yang
         * berkaitan dengan jurnal/jadwalnya.
         */
        if (session('role') === 'guru') {
            $punyaAkses = $dispensasi->id_guru_piket === (int) session('id_pengguna')
                || $dispensasi->id_guru === (int) session('id_pengguna');

            abort_unless($punyaAkses, 403);
        }

        $this->dispensasi = $dispensasi;
    }

    public function getPenerimaProperty()
    {
        return DB::table('dispensasi_penerima')
            ->join('pengguna', 'dispensasi_penerima.id_guru', '=', 'pengguna.id_pengguna')
            ->where('dispensasi_penerima.id_dispensasi', $this->dispensasi->id_dispensasi)
            ->select('pengguna.nama', 'pengguna.nip', 'dispensasi_penerima.dibaca_at')
            ->orderBy('pengguna.nama')
            ->get();
    }
};
?>

<x-layouts.detail-page
    :title="'Detail Dispensasi — '.($dispensasi->siswa?->nama_siswa ?? 'Siswa')"
    :subtitle="($dispensasi->kelas?->nama_kelas ?? '-').' · '.\Carbon\Carbon::parse($dispensasi->tanggal)->locale('id')->translatedFormat('l, d F Y')"
    backRoute="rekap-dispensasi"
>
    <x-slot:actions>
        @if ($dispensasi->lampiran_path)
        <a href="{{ route('surat-dispensasi.lampiran', ['id' => $dispensasi->id_dispensasi, 'token' => $dispensasi->token]) }}"
            class="btn btn-sm btn-outline-primary">Unduh Lampiran</a>
        @endif
    </x-slot:actions>

    <div class="row g-4">
        {{-- INFORMASI --}}
        <div class="col-12 col-lg-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Informasi Surat</div>
                <div class="p-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Nomor Surat</dt>
                        <dd class="col-sm-8">{{ $dispensasi->nomor_surat ?: 'Belum dibuat' }}</dd>

                        <dt class="col-sm-4 text-muted">Siswa</dt>
                        <dd class="col-sm-8">{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Kelas</dt>
                        <dd class="col-sm-8">{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Jenis Surat</dt>
                        <dd class="col-sm-8">{{ $dispensasi->jenis_surat ?? 'Dispensasi' }}</dd>

                        <dt class="col-sm-4 text-muted">Jenis</dt>
                        <dd class="col-sm-8">{{ $dispensasi->jenis_dispensasi }}</dd>

                        <dt class="col-sm-4 text-muted">Tanggal</dt>
                        <dd class="col-sm-8">{{ \Carbon\Carbon::parse($dispensasi->tanggal)->locale('id')->translatedFormat('d F Y') }}</dd>

                        <dt class="col-sm-4 text-muted">Waktu</dt>
                        <dd class="col-sm-8">
                            @if ($dispensasi->jenis_dispensasi === 'Per Jam')
                                Jam ke-{{ $dispensasi->jam_ke_mulai }}
                                @if ($dispensasi->jam_ke_selesai && $dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)
                                    s/d {{ $dispensasi->jam_ke_selesai }}
                                @endif
                                @if ($dispensasi->jam_mulai && $dispensasi->jam_selesai)
                                    ({{ substr($dispensasi->jam_mulai, 0, 5) }}&ndash;{{ substr($dispensasi->jam_selesai, 0, 5) }})
                                @endif
                            @else
                                Sehari penuh
                            @endif
                        </dd>

                        @if ($dispensasi->jenis_dispensasi === 'Per Mapel')
                        <dt class="col-sm-4 text-muted">Mata Pelajaran</dt>
                        <dd class="col-sm-8">{{ $dispensasi->mapel ?: '-' }}</dd>

                        <dt class="col-sm-4 text-muted">Guru Pengajar</dt>
                        <dd class="col-sm-8">{{ $dispensasi->guruPengajar?->nama ?? '-' }}</dd>
                        @endif

                        <dt class="col-sm-4 text-muted">Status</dt>
                        <dd class="col-sm-8">
                            <span @class(['badge',
                                'bg-success' => $dispensasi->status === Dispensasi::STATUS_DISETUJUI,
                                'bg-danger' => $dispensasi->status === Dispensasi::STATUS_DITOLAK,
                                'bg-warning text-dark' => $dispensasi->status === Dispensasi::STATUS_MENUNGGU,
                            ])>
                                {{ $dispensasi->status }}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- PIHAK TERKAIT --}}
        <div class="col-12 col-lg-6">
            <div class="card-custom mb-4">
                <div class="card-header-custom">Pihak Terkait</div>
                <div class="p-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Diajukan oleh (Guru Piket)</dt>
                        <dd class="col-sm-7">{{ $dispensasi->guruPiket?->nama ?? '-' }}</dd>

                        <dt class="col-sm-5 text-muted">Disetujui oleh (Wakasek)</dt>
                        <dd class="col-sm-7">{{ $dispensasi->wakasek?->nama ?? '-' }}</dd>

                        <dt class="col-sm-5 text-muted">Waktu Persetujuan</dt>
                        <dd class="col-sm-7">
                            {{ $dispensasi->waktu_approval
                                ? \Carbon\Carbon::parse($dispensasi->waktu_approval)->locale('id')->translatedFormat('d F Y H:i')
                                : '-' }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-header-custom">Alasan</div>
                <div class="p-4">{{ $dispensasi->alasan ?: '-' }}</div>
            </div>
        </div>

        {{-- CATATAN WAKASEK --}}
        @if ($dispensasi->catatan_wakasek)
        <div class="col-12">
            <div class="card-custom">
                <div class="card-header-custom text-warning">Catatan Wakasek</div>
                <div class="p-4">{{ $dispensasi->catatan_wakasek }}</div>
            </div>
        </div>
        @endif

        {{-- PENERIMA NOTIFIKASI (guru yang mengajar) --}}
        @if (count($this->penerima))
        <div class="col-12">
            <div class="card-custom">
                <div class="card-header-custom">Penerima Notifikasi (Guru Pengajar)</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle table-sm">
                        <thead class="table-light">
                            <tr><th>Nama Guru</th><th>NIP</th><th class="text-center">Status Baca</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($this->penerima as $guru)
                            <tr>
                                <td>{{ $guru->nama }}</td>
                                <td>{{ $guru->nip }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $guru->dibaca_at ? 'bg-secondary' : 'bg-primary' }}">
                                        {{ $guru->dibaca_at ? 'Sudah dibaca' : 'Belum dibaca' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-layouts.detail-page>