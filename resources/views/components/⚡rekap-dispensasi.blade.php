<?php

use Livewire\Component;
use App\Models\Dispensasi;
use Carbon\Carbon;

new class extends Component
{
    public $mode = 'mingguan';

    public $tanggalAwal;
    public $tanggalAkhir;

    public function mount()
    {
        $sekarang = Carbon::now('Asia/Jakarta');

        $this->tanggalAwal = $sekarang
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->format('Y-m-d');

        $this->tanggalAkhir = $sekarang
            ->copy()
            ->endOfWeek(Carbon::SUNDAY)
            ->format('Y-m-d');
    }

    public function updatedMode()
    {
        $sekarang = Carbon::now('Asia/Jakarta');

        if ($this->mode === 'mingguan') {

            $this->tanggalAwal = $sekarang
                ->copy()
                ->startOfWeek(Carbon::MONDAY)
                ->format('Y-m-d');

            $this->tanggalAkhir = $sekarang
                ->copy()
                ->endOfWeek(Carbon::SUNDAY)
                ->format('Y-m-d');

        } else {

            $this->tanggalAwal = $sekarang
                ->copy()
                ->startOfMonth()
                ->format('Y-m-d');

            $this->tanggalAkhir = $sekarang
                ->copy()
                ->endOfMonth()
                ->format('Y-m-d');
        }
    }

    public function getRekapProperty()
    {
        return Dispensasi::with([
            'siswa',
            'kelas',
            'guruPiket'
        ])
        ->whereBetween('tanggal', [
            $this->tanggalAwal,
            $this->tanggalAkhir
        ])
        ->orderBy('tanggal')
        ->orderBy('jam_ke_mulai')
        ->get();
    }

    public function getTotalProperty()
    {
        return $this->rekap->count();
    }

    public function getPerJamProperty()
    {
        return $this->rekap
            ->where('jenis_dispensasi', 'Per Jam')
            ->count();
    }

    public function getSehariPenuhProperty()
    {
        return $this->rekap
            ->where('jenis_dispensasi', 'Sehari Penuh')
            ->count();
    }

    public function getJumlahSiswaProperty()
    {
        return $this->rekap
            ->pluck('id_siswa')
            ->unique()
            ->count();
    }
};
?>

<div>

    {{-- HEADER --}}
    <div style="
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    ">

        <div>
            <h2 style="
                margin: 0 0 5px 0;
                color: #111827;
            ">
                Rekap Dispensasi
            </h2>

            <p style="
                margin: 0;
                color: #6b7280;
            ">
                Rekap surat dispensasi siswa berdasarkan tanggal berlaku.
            </p>
        </div>

    </div>


    {{-- PILIH PERIODE --}}
    <div style="
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    ">

        <div class="row g-3">

            <div class="col-12 col-md-4">
                <label style="
                    display: block;
                    margin-bottom: 7px;
                    font-weight: 600;
                ">
                    Jenis Rekap
                </label>

                <select
                    wire:model.live="mode"
                    style="
                        width: 100%;
                        padding: 10px 12px;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                        background: white;
                    "
                >
                    <option value="mingguan">Mingguan</option>
                    <option value="bulanan">Bulanan</option>
                </select>
            </div>


            <div class="col-6 col-md-4">
                <label style="
                    display: block;
                    margin-bottom: 7px;
                    font-weight: 600;
                ">
                    Dari
                </label>

                <input
                    type="date"
                    wire:model.live="tanggalAwal"
                    style="
                        width: 100%;
                        padding: 10px 12px;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                    "
                >
            </div>


            <div class="col-6 col-md-4">
                <label style="
                    display: block;
                    margin-bottom: 7px;
                    font-weight: 600;
                ">
                    Sampai
                </label>

                <input
                    type="date"
                    wire:model.live="tanggalAkhir"
                    style="
                        width: 100%;
                        padding: 10px 12px;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                    "
                >
            </div>

        </div>

    </div>


    {{-- STATISTIK --}}
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                height: 100%;
            ">
                <div style="color: #6b7280;">
                    Total Dispensasi
                </div>

                <div style="
                    font-size: 30px;
                    font-weight: 700;
                    margin-top: 8px;
                ">
                    {{ $this->total }}
                </div>
            </div>
        </div>


        <div class="col-6 col-md-3">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                height: 100%;
            ">
                <div style="color: #6b7280;">
                    Per Jam
                </div>

                <div style="
                    font-size: 30px;
                    font-weight: 700;
                    margin-top: 8px;
                ">
                    {{ $this->perJam }}
                </div>
            </div>
        </div>


        <div class="col-6 col-md-3">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                height: 100%;
            ">
                <div style="color: #6b7280;">
                    Sehari Penuh
                </div>

                <div style="
                    font-size: 30px;
                    font-weight: 700;
                    margin-top: 8px;
                ">
                    {{ $this->sehariPenuh }}
                </div>
            </div>
        </div>


        <div class="col-6 col-md-3">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                height: 100%;
            ">
                <div style="color: #6b7280;">
                    Siswa
                </div>

                <div style="
                    font-size: 30px;
                    font-weight: 700;
                    margin-top: 8px;
                ">
                    {{ $this->jumlahSiswa }}
                </div>
            </div>
        </div>

    </div>


    {{-- JUDUL PERIODE --}}
    <div style="
        margin-bottom: 15px;
    ">

        <h3 style="
            margin: 0;
            color: #111827;
        ">
            Rekap
            {{ $mode === 'mingguan' ? 'Mingguan' : 'Bulanan' }}
        </h3>

        <p style="
            margin: 5px 0 0;
            color: #6b7280;
        ">
            {{ Carbon::parse($tanggalAwal)->locale('id')->translatedFormat('d F Y') }}
            -
            {{ Carbon::parse($tanggalAkhir)->locale('id')->translatedFormat('d F Y') }}
        </p>

    </div>


    {{-- TABEL --}}
    <div style="
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow-x: auto;
    ">

        <table style="
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        ">

            <thead>

                <tr style="
                    background: #f3f4f6;
                    border-bottom: 1px solid #e5e7eb;
                ">

                    <th style="padding: 13px; text-align: center;">
                        No
                    </th>

                    <th style="padding: 13px; text-align: left;">
                        Tanggal
                    </th>

                    <th style="padding: 13px; text-align: left;">
                        Siswa
                    </th>

                    <th style="padding: 13px; text-align: left;">
                        Kelas
                    </th>

                    <th style="padding: 13px; text-align: center;">
                        Jenis
                    </th>

                    <th style="padding: 13px; text-align: center;">
                        Jam
                    </th>

                    <th style="padding: 13px; text-align: left;">
                        Alasan
                    </th>

                    <th style="padding: 13px; text-align: left;">
                        Guru Piket yang Menangani
                    </th>

                    <th style="padding: 13px; text-align: center;">
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse ($this->rekap as $index => $dispensasi)

                    <tr style="
                        border-bottom: 1px solid #f0f0f0;
                    ">

                        <td style="
                            padding: 13px;
                            text-align: center;
                        ">
                            {{ $index + 1 }}
                        </td>


                        <td style="padding: 13px;">
                            {{ $dispensasi->tanggal
                                ? $dispensasi->tanggal->locale('id')->translatedFormat('d F Y')
                                : '-'
                            }}
                        </td>


                        <td style="
                            padding: 13px;
                            font-weight: 600;
                        ">
                            {{ $dispensasi->siswa->nama_siswa ?? '-' }}
                        </td>


                        <td style="padding: 13px;">
                            {{ $dispensasi->kelas->nama_kelas ?? '-' }}
                        </td>


                        <td style="
                            padding: 13px;
                            text-align: center;
                        ">

                            @if ($dispensasi->jenis_dispensasi === 'Per Jam')

                                <span style="
                                    padding: 5px 10px;
                                    border-radius: 20px;
                                    background: #dbeafe;
                                    color: #1d4ed8;
                                    font-size: 13px;
                                ">
                                    Per Jam
                                </span>

                            @else

                                <span style="
                                    padding: 5px 10px;
                                    border-radius: 20px;
                                    background: #ede9fe;
                                    color: #6d28d9;
                                    font-size: 13px;
                                ">
                                    Sehari Penuh
                                </span>

                            @endif

                        </td>


                        <td style="
                            padding: 13px;
                            text-align: center;
                        ">

                            @if ($dispensasi->jenis_dispensasi === 'Per Jam')

                                Jam ke-{{ $dispensasi->jam_ke_mulai }}

                                @if ($dispensasi->jam_ke_selesai &&
                                    $dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)

                                    s/d {{ $dispensasi->jam_ke_selesai }}

                                @endif

                            @else

                                -

                            @endif

                        </td>


                        <td style="
                            padding: 13px;
                            max-width: 250px;
                        ">
                            {{ $dispensasi->alasan }}
                        </td>


                        <td style="padding: 13px;">

                            <div style="
                                font-weight: 600;
                            ">
                                {{ $dispensasi->guruPiket->nama ?? '-' }}
                            </div>

                            @if ($dispensasi->guruPiket)
                                <div style="
                                    font-size: 12px;
                                    color: #6b7280;
                                    margin-top: 3px;
                                ">
                                    {{ $dispensasi->guruPiket->nip }}
                                </div>
                            @endif

                        </td>


                        <td style="
                            padding: 13px;
                            text-align: center;
                        ">

                            {{ $dispensasi->status }}

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="9"
                            style="
                                padding: 40px;
                                text-align: center;
                                color: #6b7280;
                            "
                        >
                            Belum ada dispensasi pada periode ini.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>