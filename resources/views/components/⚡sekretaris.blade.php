<?php

use Livewire\Component;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Jurnal;
use App\Models\Jadwal;

new class extends Component
{
    public function mount()
    {
        // Belum login
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // Hanya untuk sekretaris
        if (session('role') !== 'sekretaris') {
            $this->redirectRoute('dashboard');
            return;
        }
    }

    public function getKelasProperty()
    {
        return Kelas::where('id_kelas', 4)->first();
    }

    public function getSiswaProperty()
    {
        return Siswa::where('id_kelas', 4)
            ->orderBy('id_siswa')
            ->get();
    }

    // Jurnal yang jam mengajarnya sudah selesai
    // dan masih menunggu validasi
    public function getJurnalMenungguProperty()
    {
        $hariMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        $hari = $hariMap[now()->format('l')] ?? null;
        $jamSekarang = now()->format('H:i:s');

        if (!$hari) {
            return collect();
        }

        // Jadwal yang jam mengajarnya sudah selesai
        $jadwalSelesai = Jadwal::where('hari', $hari)
            ->where('jam_selesai', '<=', $jamSekarang)
            ->get();

        // Jurnal hari ini yang masih menunggu
        $jurnal = Jurnal::whereDate('tanggal', today())
            ->where('status_validasi', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderBy('jam_ke')
            ->get();

        // Hanya tampilkan jurnal yang jadwalnya sudah selesai
        return $jurnal->filter(function ($item) use ($jadwalSelesai) {

            return $jadwalSelesai->contains(function ($jadwal) use ($item) {

                return $jadwal->id_guru == $item->id_guru
                    && $jadwal->id_kelas == $item->id_kelas
                    && $jadwal->jam_ke == $item->jam_ke;
            });
        });
    }
};
?>

<div style="
    width: 100%;
    padding: 30px;
    background: #f8fafc;
    min-height: 100vh;
">

    {{-- HEADER --}}
    <div style="margin-bottom: 25px;">

        <h2 style="margin: 0 0 5px 0;">
            Dashboard Sekretaris
        </h2>

        <p style="
            color: #666;
            margin: 0;
        ">
            Data siswa kelas {{ $this->kelas?->nama_kelas ?? 'XI RPL 2' }}
        </p>

    </div>


    {{-- INFORMASI SEKRETARIS --}}
    <div style="
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #ddd;
        margin-bottom: 25px;
    ">

        <h5 style="margin-bottom: 15px;">
            Informasi Sekretaris
        </h5>

        <div>
            <strong>Nama:</strong>
            {{ session('nama') }}
        </div>

        <div>
            <strong>NIP:</strong>
            {{ session('nip') ?? '-' }}
        </div>

        <div>
            <strong>Kelas:</strong>
            {{ $this->kelas?->nama_kelas ?? 'XI RPL 2' }}
        </div>

    </div>


    {{-- DATA SISWA --}}
    <div style="
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #ddd;
    ">

        <div style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        ">

            <h5 style="margin: 0;">
                Daftar Siswa
            </h5>

            <span style="
                background: #e0f2fe;
                color: #0369a1;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 13px;
            ">
                {{ $this->siswa->count() }} Siswa
            </span>

        </div>


        <div style="overflow-x: auto;">

            <table style="
                width: 100%;
                border-collapse: collapse;
            ">

                <thead>
                    <tr style="background: #f1f5f9;">

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: center;
                        ">
                            No
                        </th>

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: left;
                        ">
                            Nama Siswa
                        </th>

                    </tr>
                </thead>

                <tbody>

                    @forelse($this->siswa as $index => $siswa)

                        <tr>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                                text-align: center;
                            ">
                                {{ $index + 1 }}
                            </td>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                            ">
                                {{ $siswa->nama_siswa }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="2"
                                style="
                                    padding: 20px;
                                    text-align: center;
                                    color: #777;
                                "
                            >
                                Belum ada data siswa.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- JURNAL MENUNGGU VALIDASI --}}
    <div style="
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #ddd;
        margin-top: 25px;
    ">

        <div style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        ">

            <h5 style="margin: 0;">
                Jurnal Menunggu Validasi
            </h5>

            <span style="
                background: #fef3c7;
                color: #92400e;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 13px;
            ">
                {{ $this->jurnalMenunggu->count() }} Jurnal
            </span>

        </div>


        <div style="overflow-x: auto;">

            <table style="
                width: 100%;
                border-collapse: collapse;
            ">

                <thead>
                    <tr style="background: #f1f5f9;">

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: center;
                        ">
                            Jam Ke
                        </th>

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: left;
                        ">
                            Guru
                        </th>

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: left;
                        ">
                            Materi
                        </th>

                        <th style="
                            padding: 12px;
                            border: 1px solid #ddd;
                            text-align: center;
                        ">
                            Status
                        </th>

                    </tr>
                </thead>


                <tbody>

                    @forelse($this->jurnalMenunggu as $jurnal)

                        <tr>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                                text-align: center;
                            ">
                                {{ $jurnal->jam_ke }}
                            </td>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                            ">
                                {{ $jurnal->guru?->nama ?? '-' }}
                            </td>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                            ">
                                {{ $jurnal->materi }}
                            </td>

                            <td style="
                                padding: 10px;
                                border: 1px solid #ddd;
                                text-align: center;
                            ">

                                <span style="
                                    background: #fef3c7;
                                    color: #92400e;
                                    padding: 5px 10px;
                                    border-radius: 15px;
                                    font-size: 12px;
                                ">
                                    Menunggu
                                </span>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                style="
                                    padding: 20px;
                                    text-align: center;
                                    color: #777;
                                "
                            >
                                Belum ada jurnal yang menunggu validasi.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- LOGOUT --}}
    <div style="margin-top: 20px;">

        <a
            href="{{ route('logout') }}"
            style="
                display: inline-block;
                padding: 10px 18px;
                background: #dc2626;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            "
        >
            Logout
        </a>

    </div>

</div>