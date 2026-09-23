<?php

use Livewire\Component;
use App\Models\Dispensasi;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\DispensasiJurnalService;

new class extends Component
{
    public $token;
    public $id_wakasek;
    public $dispensasi = collect();
    public $catatan_wakasek = '';

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount($token, $wakasek)
    {
        $this->token = $token;
        $this->id_wakasek = $wakasek;

        // Ambil SEMUA siswa dalam satu pengajuan
        $this->muatUlangDispensasi();

        // Pastikan ID dari URL benar-benar Wakasek
        $cekWakasek = Pengguna::where(
            'id_pengguna',
            $this->id_wakasek
        )
            ->where('role', 'wakasek')
            ->first();

        if (!$cekWakasek) {
            abort(403, 'Akun Wakasek tidak valid.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MUAT ULANG
    |--------------------------------------------------------------------------
    */

    private function muatUlangDispensasi()
    {
        $this->dispensasi = Dispensasi::with([
            'siswa',
            'kelas',
            'guruPiket',
            'wakasek',
        ])
            ->where('token', $this->token)
            ->orderBy('id_dispensasi')
            ->get();

        if ($this->dispensasi->isEmpty()) {
            abort(404, 'Data dispensasi tidak ditemukan.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SETUJUI
    |--------------------------------------------------------------------------
    */

    public function setujui()
    {
        $this->validate([
            'catatan_wakasek' => 'nullable|string|max:500',
        ]);

        $berhasil = DB::transaction(function () {

            // Kunci SEMUA data dengan token yang sama
            $dispensasiList = Dispensasi::where(
                'token',
                $this->token
            )
                ->lockForUpdate()
                ->get();

            if ($dispensasiList->isEmpty()) {
                return false;
            }

            // Kalau salah satu sudah diproses,
            // jangan proses ulang pengajuan ini.
            foreach ($dispensasiList as $dispensasi) {
                if ($dispensasi->status !== 'Menunggu Persetujuan') {
                    return false;
                }
            }

            $wakasek = Pengguna::where(
                'id_pengguna',
                $this->id_wakasek
            )
                ->where('role', 'wakasek')
                ->first();

            if (!$wakasek) {
                return false;
            }

            // Setujui SEMUA siswa
            foreach ($dispensasiList as $dispensasi) {

                $dispensasi->status = 'Disetujui';

                $dispensasi->id_wakasek =
                    $wakasek->id_pengguna;

                $dispensasi->waktu_approval =
                    Carbon::now('Asia/Jakarta');

                $dispensasi->catatan_wakasek =
                    $this->catatan_wakasek ?: null;

                $dispensasi->save();
            }

            return true;
        });

        if (!$berhasil) {
            session()->flash(
                'error',
                'Dispensasi ini sudah diproses oleh Wakasek lain.'
            );

            $this->muatUlangDispensasi();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SINKRONKAN SEMUA SISWA KE JURNAL
        |--------------------------------------------------------------------------
        */

        $dispensasiList = Dispensasi::where(
            'token',
            $this->token
        )->get();

        foreach ($dispensasiList as $dispensasi) {

            app(DispensasiJurnalService::class)
                ->sync($dispensasi);

            // Notifikasi ke Guru Piket
            DB::table('dispensasi_penerima')->updateOrInsert(
                [
                    'id_dispensasi' =>
                        $dispensasi->id_dispensasi,

                    'id_guru' =>
                        $dispensasi->id_guru_piket,
                ],
                [
                    'dibaca_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->muatUlangDispensasi();

        session()->flash(
            'success',
            'Semua siswa dalam pengajuan berhasil disetujui.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOLAK
    |--------------------------------------------------------------------------
    */

    public function tolak()
    {
        $this->validate([
            'catatan_wakasek' => 'required|string|max:500',
        ], [
            'catatan_wakasek.required' =>
                'Catatan wajib diisi saat menolak.',
        ]);

        $berhasil = DB::transaction(function () {

            // Kunci SEMUA data dengan token yang sama
            $dispensasiList = Dispensasi::where(
                'token',
                $this->token
            )
                ->lockForUpdate()
                ->get();

            if ($dispensasiList->isEmpty()) {
                return false;
            }

            foreach ($dispensasiList as $dispensasi) {
                if ($dispensasi->status !== 'Menunggu Persetujuan') {
                    return false;
                }
            }

            $wakasek = Pengguna::where(
                'id_pengguna',
                $this->id_wakasek
            )
                ->where('role', 'wakasek')
                ->first();

            if (!$wakasek) {
                return false;
            }

            // Tolak SEMUA siswa dalam pengajuan
            foreach ($dispensasiList as $dispensasi) {

                $dispensasi->status = 'Ditolak';

                $dispensasi->id_wakasek =
                    $wakasek->id_pengguna;

                $dispensasi->waktu_approval =
                    Carbon::now('Asia/Jakarta');

                $dispensasi->catatan_wakasek =
                    $this->catatan_wakasek;

                $dispensasi->save();
            }

            return true;
        });

        if (!$berhasil) {
            session()->flash(
                'error',
                'Dispensasi ini sudah diproses oleh Wakasek lain.'
            );

            $this->muatUlangDispensasi();

            return;
        }

        // Kirim notifikasi ke Guru Piket untuk semua siswa
        $dispensasiList = Dispensasi::where(
            'token',
            $this->token
        )->get();

        foreach ($dispensasiList as $dispensasi) {

            DB::table('dispensasi_penerima')->updateOrInsert(
                [
                    'id_dispensasi' =>
                        $dispensasi->id_dispensasi,

                    'id_guru' =>
                        $dispensasi->id_guru_piket,
                ],
                [
                    'dibaca_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->muatUlangDispensasi();

        session()->flash(
            'success',
            'Semua siswa dalam pengajuan berhasil ditolak.'
        );
    }
};
?>

<style>
    .approval-page {
        min-height: 100vh;
        background: #f3f6f9;
        padding: 20px 14px 35px;
        font-family: Arial, sans-serif;
    }

    .approval-container {
        width: 100%;
        max-width: 620px;
        margin: 0 auto;
    }

    .approval-header {
        background: linear-gradient(135deg, #087f5b, #12a36d);
        color: white;
        border-radius: 18px;
        padding: 22px 20px;
        margin-bottom: 16px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
    }

    .approval-logo {
        font-size: 14px;
        font-weight: 700;
        opacity: .9;
        margin-bottom: 8px;
        letter-spacing: .5px;
    }

    .approval-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
    }

    .approval-header p {
        margin: 7px 0 0;
        font-size: 14px;
        line-height: 1.5;
        opacity: .9;
    }

    .alert-box {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 15px;
        font-size: 14px;
        font-weight: 600;
    }

    .alert-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        border: 1px solid #edf0f2;
    }

    .card-title {
        font-size: 17px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 17px;
    }

    .data-row {
        padding: 11px 0;
        border-bottom: 1px solid #f0f1f3;
    }

    .data-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .data-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .data-value {
        font-size: 15px;
        color: #111827;
        font-weight: 600;
        line-height: 1.4;
        word-break: break-word;
    }

    .reason-box {
        background: #f8fafc;
        border-radius: 10px;
        padding: 12px;
        margin-top: 5px;
        color: #374151;
        font-size: 14px;
        line-height: 1.5;
    }

    .status-waiting {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        padding: 6px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-approved {
        display: inline-block;
        background: #dcfce7;
        color: #166534;
        padding: 6px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-rejected {
        display: inline-block;
        background: #fee2e2;
        color: #991b1b;
        padding: 6px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 7px;
    }

    .note-info {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 9px;
    }

    .note-input {
        width: 100%;
        min-height: 90px;
        resize: vertical;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 11px;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    .note-input:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, .10);
    }

    .button-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 15px;
    }

    .approval-button {
        border: none;
        border-radius: 11px;
        padding: 14px 10px;
        color: white;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        min-height: 50px;
    }

    .approve-button {
        background: #16a34a;
    }

    .reject-button {
        background: #dc2626;
    }

    .approval-button:active {
        transform: scale(.98);
    }

    .processed-info {
        background: #f8fafc;
        border-radius: 10px;
        padding: 12px;
        margin-top: 14px;
        font-size: 13px;
        color: #4b5563;
        line-height: 1.6;
    }

    @media (max-width: 430px) {
        .approval-page {
            padding: 12px 10px 25px;
        }

        .approval-header {
            padding: 19px 17px;
            border-radius: 15px;
        }

        .approval-header h2 {
            font-size: 21px;
        }

        .card {
            padding: 17px;
            border-radius: 14px;
        }

        .button-group {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="approval-page">

    <div class="approval-container">

        {{-- HEADER --}}
        <div class="approval-header">
            <div class="approval-logo">
                SIJAGA
            </div>

            <h2>Approval Dispensasi</h2>

            <p>
                Periksa data siswa sebelum memberikan keputusan.
            </p>
        </div>

        {{-- SUCCESS --}}
        @if (session('success'))
            <div class="alert-box alert-success">
                ✓ {{ session('success') }}
            </div>
        @endif

        {{-- ERROR --}}
        @if (session('error'))
            <div class="alert-box alert-error">
                ⚠ {{ session('error') }}
            </div>
        @endif

        {{-- DATA DISPENSASI --}}
<div class="card">

    <div class="card-title">
        📋 Data Dispensasi
    </div>

    {{-- DAFTAR SISWA --}}
    <div class="data-row">
        <div class="data-label">
            Daftar Siswa
        </div>

        <div style="margin-top: 8px;">

            @foreach ($dispensasi as $item)
                <div
                    style="
                        background:#f8fafc;
                        border:1px solid #e5e7eb;
                        border-radius:10px;
                        padding:12px;
                        margin-bottom:8px;
                    "
                >
                    <div style="font-weight:700;">
                        {{ $item->siswa->nama_siswa ?? '-' }}
                    </div>

                    <div
                        style="
                            font-size:13px;
                            color:#6b7280;
                            margin-top:3px;
                        "
                    >
                        {{ $item->kelas->nama_kelas ?? '-' }}
                    </div>
                </div>
            @endforeach

        </div>
    </div>

    {{-- JENIS DISPENSASI --}}
    <div class="data-row">
        <div class="data-label">
            Jenis Dispensasi
        </div>

        <div class="data-value">
            {{ $dispensasi->first()->jenis_dispensasi }}
        </div>
    </div>

    {{-- TANGGAL --}}
    <div class="data-row">
        <div class="data-label">
            Tanggal
        </div>

        <div class="data-value">
            {{ optional($dispensasi->first()->tanggal)->translatedFormat('d F Y') }}
        </div>
    </div>

    {{-- JAM --}}
    @if ($dispensasi->first()->jenis_dispensasi === 'Per Jam')

        <div class="data-row">

            <div class="data-label">
                Jam Ke
            </div>

            <div class="data-value">

                {{ $dispensasi->first()->jam_ke_mulai }}

                @if (
                    $dispensasi->first()->jam_ke_selesai &&
                    $dispensasi->first()->jam_ke_selesai !=
                    $dispensasi->first()->jam_ke_mulai
                )
                    s/d
                    {{ $dispensasi->first()->jam_ke_selesai }}
                @endif

            </div>

        </div>

    @endif

    {{-- ALASAN --}}
    <div class="data-row">

        <div class="data-label">
            Alasan
        </div>

        <div class="reason-box">
            {{ $dispensasi->first()->alasan }}
        </div>

    </div>

    {{-- GURU PIKET --}}
    <div class="data-row">

        <div class="data-label">
            Diajukan oleh Guru Piket
        </div>

        <div class="data-value">
            {{ $dispensasi->first()->guruPiket->nama ?? '-' }}
        </div>

    </div>

    {{-- STATUS --}}
    <div class="data-row">

        <div class="data-label">
            Status
        </div>

        @php
            $status = $dispensasi->first()->status;
        @endphp

        @if ($status === 'Menunggu Persetujuan')

            <span class="status-waiting">
                ⏳ Menunggu Persetujuan
            </span>

        @elseif ($status === 'Disetujui')

            <span class="status-approved">
                ✓ Disetujui
            </span>

        @elseif ($status === 'Ditolak')

            <span class="status-rejected">
                ✕ Ditolak
            </span>

        @else

            <span class="status-waiting">
                {{ $status }}
            </span>

        @endif

    </div>

    {{-- DETAIL SETELAH DIPROSES --}}
    @if ($status !== 'Menunggu Persetujuan')

        <div class="processed-info">

            <strong>Diproses oleh:</strong>
            {{ $dispensasi->first()->wakasek->nama ?? '-' }}

            <br>

            <strong>Waktu:</strong>

            {{ optional($dispensasi->first()->waktu_approval)
                ->timezone('Asia/Jakarta')
                ->translatedFormat('d F Y, H:i') }}

            WIB

            @if ($dispensasi->first()->catatan_wakasek)

                <br><br>

                <strong>Catatan:</strong>
                {{ $dispensasi->first()->catatan_wakasek }}

            @endif

        </div>

    @endif

</div>

        {{-- FORM APPROVAL --}}
        @if ($dispensasi->first()->status === 'Menunggu Persetujuan')

            <div class="card">

                <div class="card-title">
                    ✍️ Keputusan Wakasek
                </div>

                <div class="note-info">
                    Catatan wajib diisi jika dispensasi ditolak.
                </div>

                <label class="form-label">
                    Catatan
                </label>

                <textarea
                    wire:model="catatan_wakasek"
                    class="note-input"
                    placeholder="Tulis catatan jika diperlukan..."
                ></textarea>

                @error('catatan_wakasek')
                    <div style="color:#dc2626; font-size:12px; margin-top:6px;">
                        {{ $message }}
                    </div>
                @enderror

                <div class="button-group">

                    <button
                        wire:click="setujui"
                        wire:confirm="Apakah Anda yakin ingin menyetujui dispensasi ini?"
                        class="approval-button approve-button"
                    >
                        ✓ Setujui
                    </button>

                    <button
                        wire:click="tolak"
                        wire:confirm="Apakah Anda yakin ingin menolak dispensasi ini?"
                        class="approval-button reject-button"
                    >
                        ✕ Tolak
                    </button>

                </div>

            </div>

        @endif

    </div>

</div>