<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\AbsensiSiswa;
use App\Models\Jadwal;
use Carbon\Carbon;

new class extends Component
{

public function getJadwalBerlangsungProperty()
{
    $sekarang = Carbon::now('Asia/Jakarta');

    $hari = $sekarang
        ->locale('id')
        ->translatedFormat('l');

    $jam = $sekarang->format('H:i:s');

    return Jadwal::query()
        ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
        ->join('kelas', 'jadwal.id_kelas', '=', 'kelas.id_kelas')
        ->where('jadwal.hari', $hari)
        ->where('jadwal.jam_mulai', '<=', $jam)
        ->where('jadwal.jam_selesai', '>=', $jam)
        ->select(
            'jadwal.*',
            'pengguna.nama as nama_guru',
            'pengguna.mapel_diampu',
            'kelas.nama_kelas'
        )
        ->orderBy('jadwal.jam_ke')
        ->get();
}
    public $jurnalTerpilih = null;
    public $catatanValidasi = '';

    /*
    |--------------------------------------------------------------------------
    | JURNAL MENUNGGU
    |--------------------------------------------------------------------------
    */

    public function getMenungguProperty()
    {
        return Jurnal::where('status_validasi', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | STATISTIK
    |--------------------------------------------------------------------------
    */

    public function getJumlahMenungguProperty()
    {
        return Jurnal::where('status_validasi', 'Menunggu')->count();
    }

    public function getJumlahDivalidasiProperty()
    {
        return Jurnal::where('status_validasi', 'Divalidasi')->count();
    }

    public function getJumlahDitolakProperty()
    {
        return Jurnal::where('status_validasi', 'Ditolak')->count();
    }

    /*
    |--------------------------------------------------------------------------
    | PERIKSA JURNAL
    |--------------------------------------------------------------------------
    */

    public function periksa($id)
    {
        $this->jurnalTerpilih = Jurnal::with([
            'guru',
            'kelas'
        ])->findOrFail($id);

        $this->catatanValidasi = '';
    }

    /*
    |--------------------------------------------------------------------------
    | TUTUP DETAIL
    |--------------------------------------------------------------------------
    */

    public function tutupDetail()
    {
        $this->jurnalTerpilih = null;
        $this->catatanValidasi = '';
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI JURNAL
    |--------------------------------------------------------------------------
    */

    public function validasi()
    {
        if (!$this->jurnalTerpilih) {
            return;
        }

        $this->jurnalTerpilih->update([
            'status_validasi' => 'Divalidasi',
            'id_validator' => session('id_pengguna'),
            'tanggal_validasi' => now(),
            'catatan_validasi' => $this->catatanValidasi ?: null,
        ]);

        $this->jurnalTerpilih = null;
        $this->catatanValidasi = '';

        session()->flash(
            'success',
            'Jurnal berhasil divalidasi.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOLAK JURNAL
    |--------------------------------------------------------------------------
    */

    public function tolak()
    {
        if (!$this->jurnalTerpilih) {
            return;
        }

        if (trim($this->catatanValidasi) === '') {
            $this->addError(
                'catatanValidasi',
                'Catatan penolakan wajib diisi.'
            );

            return;
        }

        $this->jurnalTerpilih->update([
            'status_validasi' => 'Ditolak',
            'id_validator' => session('id_pengguna'),
            'tanggal_validasi' => now(),
            'catatan_validasi' => $this->catatanValidasi,
        ]);

        $this->jurnalTerpilih = null;
        $this->catatanValidasi = '';

        session()->flash(
            'success',
            'Jurnal ditolak dan dikembalikan kepada guru.'
        );
    }
};
?>

<div>

    {{-- HEADER --}}
<div style="
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
">

    <div>
        <h2 style="margin: 0 0 5px 0;">
            Dashboard Guru Piket
        </h2>

        <p style="
            color: #666;
            margin: 0;
        ">
            Jurnal mengajar guru yang menunggu validasi
        </p>
    </div>

    {{-- JAM KANAN ATAS --}}
    <div style="
        text-align: right;
        flex-shrink: 0;
    ">

        <div
            id="jam-guru-piket"
            style="
                font-size: 30px;
                font-weight: 700;
                color: #1e3a8a;
                line-height: 1;
            "
        >
            {{ now('Asia/Jakarta')->format('H:i:s') }}
        </div>

        <div style="
            margin-top: 6px;
            color: #666;
            font-size: 14px;
        ">
            {{ now('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y') }}
        </div>

    </div>

</div>


    {{-- PESAN SUKSES --}}
    @if (session()->has('success'))

        <div style="
            background: #dcfce7;
            color: #166534;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
        ">
            ✓ {{ session('success') }}
        </div>

    @endif

    {{-- GURU YANG SEDANG MENGAJAR --}}
<div
    wire:poll.30s
    style="
        background: white;
        border-radius: 12px;
        border: 1px solid #ddd;
        padding: 20px;
        margin-bottom: 30px;
    "
>

    <h3 style="
        margin: 0 0 5px 0;
    ">
        👨‍🏫 Guru yang Sedang Mengajar
    </h3>

    <p style="
        margin: 0 0 20px 0;
        color: #777;
    ">
        Berdasarkan jadwal dan waktu saat ini.
    </p>

    @forelse ($this->jadwalBerlangsung as $jadwal)

        <div style="
            padding: 18px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 5px solid #2563eb;
            margin-bottom: 12px;
        ">

            <div style="
                display: flex;
                justify-content: space-between;
                gap: 20px;
                flex-wrap: wrap;
            ">

                <div>

                    <div style="
                        font-size: 18px;
                        font-weight: 700;
                        color: #111827;
                    ">
                        {{ $jadwal->nama_guru }}
                    </div>

                    <div style="
                        margin-top: 5px;
                        color: #555;
                    ">
                        📚 {{ $jadwal->mapel_diampu ?? '-' }}
                    </div>

                    <div style="
                        margin-top: 5px;
                        color: #555;
                    ">
                        🏫 {{ $jadwal->nama_kelas }}
                    </div>

                </div>

                <div style="
                    text-align: right;
                ">

                    <div style="
                        font-weight: 700;
                        color: #2563eb;
                    ">
                        Jam ke-{{ $jadwal->jam_ke }}
                    </div>

                    <div style="
                        margin-top: 5px;
                        color: #666;
                    ">
                        {{ \Carbon\Carbon::parse($jadwal->jam_mulai)->format('H:i') }}
                        -
                        {{ \Carbon\Carbon::parse($jadwal->jam_selesai)->format('H:i') }}
                    </div>

                </div>

            </div>

        </div>

    @empty

        <div style="
            padding: 25px;
            text-align: center;
            background: #f8fafc;
            border-radius: 10px;
            color: #777;
        ">
            📭 Tidak ada guru yang sedang mengajar saat ini.
        </div>

    @endforelse

</div>


    {{-- STATISTIK --}}
    <div class="row g-3 mb-4">

        {{-- MENUNGGU --}}
        <div class="col-6 col-md-4">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #ddd;
                height: 100%;
            ">

                <div style="color: #777;">
                    Menunggu Validasi
                </div>

                <h1 style="margin: 10px 0;">
                    {{ $this->jumlahMenunggu }}
                </h1>

                <small>
                    Jurnal belum diperiksa
                </small>

            </div>
        </div>


        {{-- DIVALIDASI --}}
        <div class="col-6 col-md-4">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #ddd;
                height: 100%;
            ">

                <div style="color: #777;">
                    Divalidasi
                </div>

                <h1 style="margin: 10px 0;">
                    {{ $this->jumlahDivalidasi }}
                </h1>

                <small>
                    Jurnal sudah disetujui
                </small>

            </div>
        </div>


        {{-- DITOLAK --}}
        <div class="col-6 col-md-4">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #ddd;
                height: 100%;
            ">

                <div style="color: #777;">
                    Ditolak
                </div>

                <h1 style="margin: 10px 0;">
                    {{ $this->jumlahDitolak }}
                </h1>

                <small>
                    Jurnal perlu diperbaiki
                </small>

            </div>
        </div>

    </div>


    {{-- JURNAL MASUK --}}
    <div style="
        background: white;
        border-radius: 12px;
        border: 1px solid #ddd;
        overflow: hidden;
    ">

        <div style="
            padding: 20px;
            border-bottom: 1px solid #ddd;
        ">

            <h3 style="margin: 0;">
                🔔 Jurnal Masuk
            </h3>

            <p style="
                margin: 5px 0 0;
                color: #777;
            ">
                Jurnal guru yang menunggu konfirmasi dan validasi.
            </p>

        </div>


        <div style="overflow-x: auto;">

            <table style="
                width: 100%;
                border-collapse: collapse;
            ">

                <thead>

                    <tr style="background: #f5f5f5;">

                        <th style="padding: 12px; text-align: left;">
                            No
                        </th>

                        <th style="padding: 12px; text-align: left;">
                            Tanggal
                        </th>

                        <th style="padding: 12px; text-align: left;">
                            Guru
                        </th>

                        <th style="padding: 12px; text-align: left;">
                            Kelas
                        </th>

                        <th style="padding: 12px; text-align: left;">
                            Jam
                        </th>

                        <th style="padding: 12px; text-align: left;">
                            Materi
                        </th>

                        <th style="padding: 12px; text-align: center;">
                            Status
                        </th>

                        <th style="padding: 12px; text-align: center;">
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($this->menunggu as $index => $jurnal)

                        <tr style="
                            border-top: 1px solid #eee;
                        ">

                            <td style="padding: 12px;">
                                {{ $index + 1 }}
                            </td>

                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $jurnal->guru->nama ?? '-' }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $jurnal->kelas->nama_kelas ?? '-' }}
                            </td>

                            <td style="padding: 12px;">
                                Jam {{ $jurnal->jam_ke }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $jurnal->materi }}
                            </td>

                            <td style="
                                padding: 12px;
                                text-align: center;
                            ">

                                <span style="
                                    display: inline-block;
                                    padding: 5px 10px;
                                    border-radius: 20px;
                                    background: #fef3c7;
                                    color: #92400e;
                                    font-size: 13px;
                                ">
                                    Menunggu
                                </span>

                            </td>

                            <td style="
                                padding: 12px;
                                text-align: center;
                            ">

                                <button
                                    wire:click="periksa({{ $jurnal->id_jurnal }})"
                                    style="
                                        padding: 8px 14px;
                                        border: none;
                                        border-radius: 8px;
                                        background: #2563eb;
                                        color: white;
                                        cursor: pointer;
                                    "
                                >
                                    Periksa
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                style="
                                    padding: 40px;
                                    text-align: center;
                                    color: #777;
                                "
                            >

                                ✓ Tidak ada jurnal yang menunggu validasi.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- DETAIL JURNAL --}}
    @if ($jurnalTerpilih)

        <div style="
            margin-top: 25px;
            background: white;
            border-radius: 12px;
            border: 1px solid #ddd;
            overflow: hidden;
        ">

            {{-- HEADER DETAIL --}}
            <div style="
                padding: 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">

                <div>

                    <h3 style="margin: 0;">
                        Detail Jurnal
                    </h3>

                    <p style="
                        margin: 5px 0 0;
                        color: #777;
                    ">
                        Periksa jurnal sebelum melakukan validasi.
                    </p>

                </div>

                <button
                    wire:click="tutupDetail"
                    style="
                        border: none;
                        background: #eee;
                        padding: 8px 12px;
                        border-radius: 8px;
                        cursor: pointer;
                    "
                >
                    ✕ Tutup
                </button>

            </div>


            {{-- INFORMASI JURNAL --}}
            <div class="row g-3" style="padding: 20px;">

                <div class="col-12 col-md-6">
                    <strong>Guru</strong>
                    <div style="margin-top: 5px;">
                        {{ $jurnalTerpilih->guru->nama ?? '-' }}
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <strong>Kelas</strong>
                    <div style="margin-top: 5px;">
                        {{ $jurnalTerpilih->kelas->nama_kelas ?? '-' }}
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <strong>Tanggal</strong>
                    <div style="margin-top: 5px;">
                        {{ \Carbon\Carbon::parse($jurnalTerpilih->tanggal)->format('d F Y') }}
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <strong>Jam Ke</strong>
                    <div style="margin-top: 5px;">
                        Jam {{ $jurnalTerpilih->jam_ke }}
                    </div>
                </div>

                <div class="col-12">

                    <strong>Materi</strong>

                    <div style="
                        margin-top: 5px;
                        padding: 12px;
                        background: #f8fafc;
                        border-radius: 8px;
                    ">
                        {{ $jurnalTerpilih->materi }}
                    </div>

                </div>

                <div class="col-12">

                    <strong>Status Kehadiran Guru</strong>

                    <div style="
                        margin-top: 5px;
                    ">
                        {{ $jurnalTerpilih->status_kehadiran_guru }}
                    </div>

                </div>

                @if ($jurnalTerpilih->catatan)

                    <div class="col-12">

                        <strong>Catatan Guru</strong>

                        <div style="
                            margin-top: 5px;
                            padding: 12px;
                            background: #f8fafc;
                            border-radius: 8px;
                        ">
                            {{ $jurnalTerpilih->catatan }}
                        </div>

                    </div>

                @endif

            </div>


            {{-- ABSENSI SISWA --}}
            <div style="
                padding: 20px;
                border-top: 1px solid #eee;
            ">

                <h3 style="margin-top: 0;">
                    Absensi Siswa
                </h3>

                @php
                    $absensiSiswa = AbsensiSiswa::with('siswa')
                        ->where('id_jurnal', $jurnalTerpilih->id_jurnal)
                        ->get();

                    $hadir = $absensiSiswa->where('keterangan', 'Hadir')->count();
                    $izin = $absensiSiswa->where('keterangan', 'Izin')->count();
                    $sakit = $absensiSiswa->where('keterangan', 'Sakit')->count();
                    $alpa = $absensiSiswa->where('keterangan', 'Alpa')->count();
                    $dispensasi = $absensiSiswa->where('keterangan', 'Dispensasi')->count();
                @endphp


                <div style="
                    display: flex;
                    gap: 15px;
                    flex-wrap: wrap;
                    margin-bottom: 20px;
                ">

                    <span>Hadir: <strong>{{ $hadir }}</strong></span>

                    <span>Izin: <strong>{{ $izin }}</strong></span>

                    <span>Sakit: <strong>{{ $sakit }}</strong></span>

                    <span>Alpa: <strong>{{ $alpa }}</strong></span>

                    <span>Dispensasi: <strong>{{ $dispensasi }}</strong></span>

                </div>


                @if ($absensiSiswa->count())

                    <div style="overflow-x: auto;">

                        <table style="
                            width: 100%;
                            border-collapse: collapse;
                        ">

                            <thead>

                                <tr style="background: #f5f5f5;">

                                    <th style="padding: 10px; text-align: left;">
                                        No
                                    </th>

                                    <th style="padding: 10px; text-align: left;">
                                        Nama Siswa
                                    </th>

                                    <th style="padding: 10px; text-align: center;">
                                        Keterangan
                                    </th>

                                    <th style="padding: 10px; text-align: left;">
                                        Keterangan Dispensasi
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @foreach ($absensiSiswa as $no => $absensi)

                                    <tr style="
                                        border-top: 1px solid #eee;
                                    ">

                                        <td style="padding: 10px;">
                                            {{ $no + 1 }}
                                        </td>

                                        <td style="padding: 10px;">
                                            {{ $absensi->siswa->nama_siswa ?? '-' }}
                                        </td>

                                        <td style="
                                            padding: 10px;
                                            text-align: center;
                                        ">
                                            {{ $absensi->keterangan }}
                                        </td>

                                        <td style="padding: 10px;">
                                            {{ $absensi->keterangan_dispensasi ?? '-' }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <p style="color: #777;">
                        Belum ada data absensi siswa.
                    </p>

                @endif

            </div>


            {{-- VALIDASI --}}
            <div style="
                padding: 20px;
                border-top: 1px solid #eee;
            ">

                <h3 style="margin-top: 0;">
                    Validasi Jurnal
                </h3>

                <label>
                    Catatan Validasi
                </label>

                <textarea
                    wire:model="catatanValidasi"
                    rows="4"
                    placeholder="Isi catatan jika diperlukan. Wajib diisi jika jurnal ditolak."
                    style="
                        width: 100%;
                        margin-top: 8px;
                        padding: 12px;
                        border: 1px solid #ccc;
                        border-radius: 8px;
                        resize: vertical;
                        box-sizing: border-box;
                    "
                ></textarea>

                @error('catatanValidasi')

                    <div style="
                        color: #dc2626;
                        margin-top: 5px;
                    ">
                        {{ $message }}
                    </div>

                @enderror


                <div style="
                    margin-top: 15px;
                    display: flex;
                    gap: 10px;
                ">

                    <button
                        wire:click="tutupDetail"
                        style="
                            padding: 10px 18px;
                            border: 1px solid #ccc;
                            background: white;
                            border-radius: 8px;
                            cursor: pointer;
                        "
                    >
                        Batal
                    </button>


                    <button
                        wire:click="tolak"
                        style="
                            padding: 10px 18px;
                            border: none;
                            background: #dc2626;
                            color: white;
                            border-radius: 8px;
                            cursor: pointer;
                        "
                    >
                        Tolak
                    </button>


                    <button
                        wire:click="validasi"
                        style="
                            padding: 10px 18px;
                            border: none;
                            background: #16a34a;
                            color: white;
                            border-radius: 8px;
                            cursor: pointer;
                        "
                    >
                        ✓ Validasi
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>

<script>
    function updateJamGuruPiket() {
        const sekarang = new Date();

        const jam = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');

        const element = document.getElementById('jam-guru-piket');

        if (element) {
            element.textContent = `${jam}:${menit}:${detik}`;
        }
    }

    updateJamGuruPiket();

    setInterval(updateJamGuruPiket, 1000);
</script>