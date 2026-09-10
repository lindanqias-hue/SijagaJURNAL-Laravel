<?php

use Livewire\Component;
use App\Models\Kelas;
use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Models\AbsensiSiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;   // <-- baru, dipakai loadJadwal()

new class extends Component
{
    public $id_kelas = '';
    public $tanggal;
    public $jam_ke = 1;
    public $materi = '';
    public $jumlah_tidak_hadir = 0;
    public $status_kehadiran_guru = 'Hadir';
    public $catatan = '';

    public $jadwalAktif = null;
    public $mapelAktif = '';

    public $jamMulaiPembelajaran = null;
    public $jamSelesaiPembelajaran = null;
    public $jamMulaiKe = null;
    public $jamSelesaiKe = null;

    public $editing = null;
public $saved = false;
public $isSaving = false;

    public $siswa = [];
    public $absensi = [];
    public $keteranganDispensasi = [];

   public function mount()
{
    // Tanggal selalu otomatis mengikuti hari ini
    $this->tanggal = now()->format('Y-m-d');

    if (request()->has('edit')) {

        $id = request()->get('edit');

        $this->editing = Jurnal::where('id_jurnal', $id)
            ->where('id_guru', session('id_pengguna'))
            ->first();

        if ($this->editing) {

            if ($this->editing->status_validasi !== 'Menunggu') {
                $this->editing = null;
                return;
            }

            $this->id_kelas = $this->editing->id_kelas;

            // JANGAN mengambil tanggal dari jurnal lama
            // Tanggal tetap otomatis hari ini
            $this->tanggal = now()->format('Y-m-d');

            $this->jam_ke = $this->editing->jam_ke;
            $this->materi = $this->editing->materi;

            $this->jumlah_tidak_hadir =
                $this->editing->jumlah_tidak_hadir ?? 0;

            $this->status_kehadiran_guru =
                $this->editing->status_kehadiran_guru ?? 'Hadir';

            $this->catatan =
                $this->editing->catatan ?? '';

            // Ambil mapel guru
            $this->mapelAktif = DB::table('pengguna')
                ->where('id_pengguna', session('id_pengguna'))
                ->value('mapel_diampu') ?? '';

            // Load siswa
            $this->loadSiswa();
        }

    } else {

        // Untuk jurnal baru, cari jadwal yang sedang aktif
        $this->loadJadwal();
        $this->loadSiswa();
    }
}


/**
 * Mengambil jadwal sesuai:
 * - guru yang sedang login
 * - hari sekarang
 * - jam sekarang
 */
public function loadJadwal()
{
    $idGuru = session('id_pengguna');

    if (!$idGuru) {
        return;
    }

    // Konversi nama hari Inggris -> Indonesia
    $hariInggris = now()->format('l');

    $hariMap = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu',
    ];

    $hari = $hariMap[$hariInggris] ?? null;

    if (!$hari) {
        return;
    }

    $jamSekarang = now()->format('H:i:s');

       // Ambil semua jadwal guru ini KHUSUS untuk hari ini saja
    $jadwalHariIni = Jadwal::where('id_guru', $idGuru)
        ->where('hari', $hari)
        ->orderBy('jam_ke')
        ->get();

    // 1. Cari jadwal yang sedang berlangsung tepat sekarang
    $this->jadwalAktif = $jadwalHariIni->first(
        fn ($j) => $j->jam_mulai <= $jamSekarang && $j->jam_selesai >= $jamSekarang
    );

    // 2. Kalau tidak ada jam yang sedang berlangsung,
    // ambil jadwal HARI INI yang jam mulainya paling dekat
    if (!$this->jadwalAktif && $jadwalHariIni->isNotEmpty()) {

        $nowTime = Carbon::createFromFormat('H:i:s', $jamSekarang);

        $this->jadwalAktif = $jadwalHariIni
            ->sortBy(function ($j) use ($nowTime) {
                return abs(
                    Carbon::createFromFormat('H:i:s', $j->jam_mulai)
                        ->diffInSeconds($nowTime)
                );
            })
            ->first();
    }

    if (!$this->jadwalAktif) {
        return;
    }

    // Otomatis isi kelas
    $this->id_kelas = $this->jadwalAktif->id_kelas;

    // Jam mulai
    $this->jamMulaiKe = $this->jadwalAktif->jam_ke;
    $this->jamMulaiPembelajaran = $this->jadwalAktif->jam_mulai;

    // Default selesai = jadwal yang sedang aktif
    $this->jamSelesaiKe = $this->jadwalAktif->jam_ke;
    $this->jamSelesaiPembelajaran = $this->jadwalAktif->jam_selesai;

    /*
     * Cari jam berikutnya yang:
     * - guru sama
     * - kelas sama
     * - jam ke berikutnya
     * - waktu mulai sama dengan waktu selesai sebelumnya
     *
     * Contoh:
     * Jam 5 = 10:00 - 10:35
     * Jam 6 = 10:35 - 11:10
     *
     * Maka hasil:
     * Mulai  = Jam ke-5, 10:00
     * Selesai = Jam ke-6, 11:10
     */

    $jadwalBerikutnya = Jadwal::where('id_guru', $idGuru)
        ->where('id_kelas', $this->jadwalAktif->id_kelas)
        ->where('hari', $hari)
        ->where('jam_ke', $this->jadwalAktif->jam_ke + 1)
        ->where('jam_mulai', $this->jadwalAktif->jam_selesai)
        ->first();

    // Kalau ada jam berikutnya yang menyambung, lanjutkan
    while ($jadwalBerikutnya) {

        $this->jamSelesaiKe = $jadwalBerikutnya->jam_ke;
        $this->jamSelesaiPembelajaran = $jadwalBerikutnya->jam_selesai;

        // Cari jam berikutnya lagi
        $jadwalBerikutnya = Jadwal::where('id_guru', $idGuru)
            ->where('id_kelas', $this->jadwalAktif->id_kelas)
            ->where('hari', $hari)
            ->where('jam_ke', $jadwalBerikutnya->jam_ke + 1)
            ->where('jam_mulai', $this->jamSelesaiPembelajaran)
            ->first();
    }

    // Ambil mapel guru
    $this->mapelAktif = DB::table('pengguna')
        ->where('id_pengguna', $idGuru)
        ->value('mapel_diampu') ?? '';

    // Ambil siswa sesuai kelas
    $this->loadSiswa();
}
    public function getKelasAktifProperty()
    {
        if (!$this->id_kelas) {
            return null;
        }

        return Kelas::where('id_kelas', $this->id_kelas)->first();
    }


    public function getTotalSiswaProperty()
    {
        if (!$this->id_kelas) {
            return 0;
        }

        return Kelas::where('id_kelas', $this->id_kelas)
            ->value('jumlah_siswa') ?? 0;
    }


    public function getJumlahHadirProperty()
    {
        if ($this->status_kehadiran_guru !== 'Hadir') {
            return 0;
        }

        return max(
            $this->totalSiswa - (int) $this->jumlah_tidak_hadir,
            0
        );
    }


    public function updatedIdKelas()
    {
        $this->jumlah_tidak_hadir = 0;
        $this->loadSiswa();

    }

    public function loadSiswa()
{
    if (!$this->id_kelas) {
        $this->siswa = [];
        $this->absensi = [];
        return;
    }

    $this->siswa = Siswa::where('id_kelas', $this->id_kelas)
        ->orderBy('id_siswa')
        ->get();

    foreach ($this->siswa as $siswa) {
        if (!isset($this->absensi[$siswa->id_siswa])) {
            $this->absensi[$siswa->id_siswa] = 'Hadir';
        }
    }
}

public function save()
{

    // 1. Validasi data terlebih dahulu
    $this->validate([
        'id_kelas' => 'required|exists:kelas,id_kelas',
        'tanggal' => 'required|date',
        'jam_ke' => 'required|integer|min:1|max:12',
        'materi' => 'required|string',
        'status_kehadiran_guru' =>
            'required|in:Hadir,Izin,Sakit,Tanpa Keterangan',
    ], [
        'id_kelas.required' => 'Jadwal kelas belum ditemukan.',
        'id_kelas.exists' => 'Kelas tidak ditemukan.',
        'materi.required' => 'Materi wajib diisi.',
    ]);

    // lanjutkan kode save kamu di sini...
    // Ambil semua siswa berdasarkan kelas
    $this->loadSiswa();

    // Pastikan semua siswa memiliki status absensi
    foreach ($this->siswa as $siswa) {
        if (!isset($this->absensi[$siswa->id_siswa])) {
            $this->addError(
                'absensi',
                'Status kehadiran semua siswa harus diisi.'
            );
            return;
        }
    }

    // Hitung jumlah berdasarkan status siswa
    $jumlahHadir = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Hadir')
        ->count();

    $jumlahIzin = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Izin')
        ->count();

    $jumlahSakit = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Sakit')
        ->count();

    $jumlahAlpa = collect($this->absensi)
    ->filter(fn ($status) => $status === 'Alpa')
    ->count();

$jumlahDispensasi = collect($this->absensi)
    ->filter(fn ($status) => $status === 'Dispensasi')
    ->count();

$jumlahTidakHadir =
    $jumlahIzin +
    $jumlahSakit +
    $jumlahAlpa +
    $jumlahDispensasi;

    $data = [
        'id_guru' => session('id_pengguna'),
        'id_kelas' => $this->id_kelas,
        'tanggal' => $this->tanggal,
        'jam_ke' => $this->jam_ke,
        'materi' => $this->materi,
        'jumlah_hadir' => $jumlahHadir,
        'jumlah_tidak_hadir' => $jumlahTidakHadir,
        'status_kehadiran_guru' => $this->status_kehadiran_guru,
        'catatan' => $this->catatan ?: null,
        'status_validasi' => 'Menunggu',
        'id_validator' => null,
        'tanggal_validasi' => null,
        'catatan_validasi' => null,
    ];

    // Simpan atau update jurnal
    if ($this->editing) {

        $this->editing->update($data);

        $idJurnal = $this->editing->id_jurnal;

        // Hapus absensi lama jika sedang edit
        AbsensiSiswa::where('id_jurnal', $idJurnal)->delete();

    } else {

        $jurnal = Jurnal::create($data);

        $idJurnal = $jurnal->id_jurnal;
    }

    // Simpan absensi setiap siswa
    foreach ($this->absensi as $idSiswa => $keterangan) {
    AbsensiSiswa::create([
        'id_jurnal' => $idJurnal,
        'id_siswa' => $idSiswa,
        'keterangan' => $keterangan,
        'keterangan_dispensasi' =>
            $keterangan === 'Dispensasi'
                ? ($this->keteranganDispensasi[$idSiswa] ?? null)
                : null,
    ]);
}

    $this->saved = true;
}
};
?>

<div>

    <div class="mb-4">

        <a
            href="{{ route('riwayat') }}"
            class="text-decoration-none text-muted d-inline-block mb-2"
            style="font-size:13px;"
        >
            &larr; Kembali ke Riwayat
        </a>

        <div class="page-title">
            {{ $editing ? 'Edit Jurnal Mengajar' : 'Input Jurnal Mengajar' }}
        </div>

        <div
            class="text-muted mt-1"
            style="font-size:13px;"
        >
            {{ $editing
                ? 'Mengedit jurnal ID #' . $editing->id_jurnal . ' (akan dikirim ulang untuk divalidasi)'
                : 'Catat aktivitas pembelajaran Anda hari ini.'
            }}
        </div>

    </div>


    <form
    wire:submit="save"
    style="width:100%; max-width:none;"
>

        {{-- INFORMASI PEMBELAJARAN --}}

        <div class="form-section">

            <div class="form-section-title">
                Informasi Pembelajaran
            </div>


            @if ($jadwalAktif || $editing)

                <div class="row g-3">

                    {{-- MAPEL --}}

                    <div class="col-md-6">

                        <label class="form-label-sm">
                            Mata Pelajaran
                        </label>

                        <input
                            type="text"
                            value="{{ $mapelAktif ?: '-' }}"
                            readonly
                            class="form-control form-control-custom readonly-field"
                        >

                    </div>


                    {{-- KELAS --}}

                    <div class="col-md-6">

                        <label class="form-label-sm">
                            Kelas
                        </label>

                        <input
                            type="text"
                            value="{{ $this->kelasAktif?->nama_kelas ?? '-' }}"
                            readonly
                            class="form-control form-control-custom readonly-field"
                        >

                        @error('id_kelas')
                            <div class="text-danger" style="font-size:12px;">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    {{-- TANGGAL --}}

<div class="col-md-4">

    <label class="form-label-sm">
        Tanggal
    </label>

    <div class="form-control form-control-custom bg-light">
        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}
    </div>

</div>


                    {{-- JAM PEMBELAJARAN --}}
<div class="col-md-6">
    <label class="form-label-sm">
        Jam Pembelajaran
    </label>

    <input
        type="text"
        value="{{ $jamMulaiKe ? 'Jam ke-' . $jamMulaiKe . ' - Jam ke-' . $jamSelesaiKe : '-' }}"
        readonly
        class="form-control form-control-custom readonly-field"
    >
</div>


{{-- WAKTU PEMBELAJARAN --}}
<div class="col-md-6">
    <label class="form-label-sm">
        Waktu Pembelajaran
    </label>

    <input
        type="text"
        value="{{ $jamMulaiPembelajaran && $jamSelesaiPembelajaran
            ? substr($jamMulaiPembelajaran, 0, 5) . ' - ' . substr($jamSelesaiPembelajaran, 0, 5)
            : '-' }}"
        readonly
        class="form-control form-control-custom readonly-field"
    >
</div>


                    {{-- MATERI --}}

                    <div class="col-12">

                        <label class="form-label-sm">
                            Materi Pembelajaran
                        </label>

                        <input
                            type="text"
                            wire:model="materi"
                            placeholder="Contoh: Pengenalan DDL dan DML pada Basis Data"
                            class="form-control form-control-custom @error('materi') is-invalid @enderror"
                        >

                        @error('materi')
                            <div class="text-danger" style="font-size:12px;">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>

            @else

                <div class="alert alert-warning">
                    &#9888;
                    Tidak ada jadwal mengajar yang sedang berlangsung saat ini.
                </div>

            @endif

        </div>

        <div class="mb-3">
    <label class="form-label fw-semibold">Keterangan</label>
    <textarea
        wire:model="catatan"
        class="form-control"
        rows="3"
        placeholder="Masukkan keterangan tambahan..."
    ></textarea>
</div>


       {{-- KEHADIRAN --}}
<div class="form-section">

    <div class="form-section-title">
        Kehadiran & Absensi Siswa
    </div>

    {{-- STATUS KEHADIRAN GURU --}}
    <div class="mb-4">

        <label class="form-label-sm">
            Status Kehadiran Guru
        </label>

        <div class="d-flex flex-wrap gap-2 status-pill-radio">

            @foreach ([
                'Hadir' => '&#9989;',
                'Izin' => '&#128203;',
                'Sakit' => '&#129298;',
                'Tanpa Keterangan' => '&#10067;'
            ] as $status => $icon)

                @php
                    $rid = 'status_' . str_replace(' ', '_', $status);
                @endphp

                <input
                    type="radio"
                    id="{{ $rid }}"
                    wire:model="status_kehadiran_guru"
                    value="{{ $status }}"
                >

                <label for="{{ $rid }}">
                    {!! $icon !!} {{ $status }}
                </label>

            @endforeach

        </div>

    </div>


    {{-- DAFTAR SISWA --}}
    @if ($jadwalAktif || $editing)

        <div class="mb-3">

            <label class="form-label-sm">
                Daftar Kehadiran Siswa
            </label>

            <div class="table-responsive border rounded">

                <table class="table table-hover mb-0 align-middle">

                    <thead class="table-light">
                        <tr>

                            <th class="text-center" style="width:60px;">
                                No
                            </th>

                            <th>
                                Nama Siswa
                            </th>

                            <th class="text-center" style="width:220px;">
                                Keterangan
                            </th>

                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($siswa as $index => $dataSiswa)

                            <tr>

                                <td class="text-center">
                                    {{ $index + 1 }}
                                </td>

                                <td>
                                    <span class="fw-semibold">
                                        {{ $dataSiswa->nama_siswa }}
                                    </span>
                                </td>

                                <td>

                                    <select
                                        wire:model.live="absensi.{{ $dataSiswa->id_siswa }}"
                                        class="form-select"
                                    >

                                        <option value="Hadir">
                                            Hadir
                                        </option>

                                        <option value="Izin">
                                            Izin
                                        </option>

                                        <option value="Sakit">
                                            Sakit
                                        </option>

                                        <option value="Alpa">
                                            Alpa
                                        </option>

                                        <option value="Dispensasi">
                                            Dispensasi
                                        </option>

                                    </select>


                                    {{-- KETERANGAN DISPENSASI --}}
                                    @if (($absensi[$dataSiswa->id_siswa] ?? '') === 'Dispensasi')

                                        <input
                                            type="text"
                                            wire:model.live="keteranganDispensasi.{{ $dataSiswa->id_siswa }}"
                                            class="form-control mt-2"
                                            placeholder="Keterangan dispensasi..."
                                        >

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted py-4"
                                >
                                    Data siswa belum ditemukan.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        {{-- REKAP OTOMATIS --}}
        @php

            $totalHadir = collect($absensi)
                ->filter(fn ($status) => $status === 'Hadir')
                ->count();

            $totalIzin = collect($absensi)
                ->filter(fn ($status) => $status === 'Izin')
                ->count();

            $totalSakit = collect($absensi)
                ->filter(fn ($status) => $status === 'Sakit')
                ->count();

            $totalAlpa = collect($absensi)
                ->filter(fn ($status) => $status === 'Alpa')
                ->count();

            $totalDispensasi = collect($absensi)
                ->filter(fn ($status) => $status === 'Dispensasi')
                ->count();

            $totalTidakHadir =
                $totalIzin +
                $totalSakit +
                $totalAlpa +
                $totalDispensasi;

        @endphp


        {{-- TOTAL --}}
        <div class="row g-3 mb-3">

            <div class="col-md-6">

                <div class="info-box-total">

                    <div>
                        <div class="text-muted small">
                            TOTAL HADIR
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalHadir }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#9989;
                    </div>

                </div>

            </div>


            <div class="col-md-6">

                <div class="info-box-total">

                    <div>
                        <div class="text-muted small">
                            TOTAL TIDAK HADIR
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalTidakHadir }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#10060;
                    </div>

                </div>

            </div>

        </div>


        {{-- DETAIL REKAP --}}
        <div class="row g-3">

            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            IZIN
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalIzin }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#128203;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            SAKIT
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalSakit }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#129298;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            ALPA
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalAlpa }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#10067;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            DISPENSASI
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalDispensasi }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#128196;
                    </div>
                </div>

            </div>

        </div>

    @endif

    {{-- TOMBOL KONFIRMASI --}}
@if ($jadwalAktif || $editing)

    <div class="d-flex justify-content-center gap-3 mt-4">

        <a
            href="{{ route('riwayat') }}"
            class="btn btn-outline-secondary px-4"
        >
            Batal
        </a>

        <button
    type="submit"
    wire:confirm="Yakin jurnal ini sudah benar dan ingin dikirim ke Guru Piket untuk divalidasi?"
    class="btn btn-primary px-4"
    wire:loading.attr="disabled"
    wire:target="save"
>
    <span wire:loading.remove wire:target="save">
        @if ($status_kehadiran_guru === 'Hadir')
            ✓ Konfirmasi & Kirim ke Guru Piket
        @else
            ✓ Konfirmasi ke Guru Piket
        @endif
    </span>

    <span wire:loading wire:target="save">
        ⏳ Mengirim jurnal...
    </span>
</button>

@if ($saved)
    <div class="alert-box alert-success-box mt-3 text-center">
        <strong>✓ Jurnal berhasil dikirim ke Guru Piket.</strong>
        <br>
        Silakan tunggu pemeriksaan dan validasi dari Guru Piket.
        <br>
        <a href="{{ route('riwayat') }}" class="mt-1 d-inline-block">
            Lihat Riwayat Jurnal →
        </a>
    </div>
@endif

    </div>

    @endif