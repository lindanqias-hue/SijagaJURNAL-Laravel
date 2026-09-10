<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Jadwal;
use Carbon\Carbon;

new class extends Component
{
    public $jurnalTerpilih = null;
    public string $catatanSekretaris = '';

    /*
    |--------------------------------------------------------------------------
    | AKSES HALAMAN
    |--------------------------------------------------------------------------
    */

    public function mount()
    {
        // Belum login
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // Bukan sekretaris → tidak boleh mengakses halaman ini
        if (session('role') !== 'sekretaris') {
            $this->redirectRoute('dashboard');
            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CEK APAKAH JAM PELAJARAN SUDAH SELESAI
    |--------------------------------------------------------------------------
    | Sekretaris hanya boleh mengonfirmasi jurnal setelah jam pelajaran
    | yang bersangkutan benar-benar sudah berakhir.
    */

    private function namaHariIndonesia($tanggal): ?string
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

        $hariInggris = Carbon::parse($tanggal)->format('l');

        return $hariMap[$hariInggris] ?? null;
    }

    /**
     * Guru bisa mengajar beberapa jam pelajaran berurutan (mis. jam 7-10)
     * di kelas yang sama pada hari yang sama. Sekretaris baru boleh
     * mengonfirmasi setelah JAM TERAKHIR dalam rangkaian tersebut selesai,
     * bukan cuma setelah jam pertama jurnal ini berakhir.
     */
    public function jamKeTerakhirBlok(Jurnal $jurnal): int
    {
        $hari = $this->namaHariIndonesia($jurnal->tanggal);

        if (!$hari) {
            return (int) $jurnal->jam_ke;
        }

        $jadwalHariItu = Jadwal::where('id_guru', $jurnal->id_guru)
            ->where('id_kelas', $jurnal->id_kelas)
            ->where('hari', $hari)
            ->orderBy('jam_ke')
            ->get();

        // Telusuri jam_ke berurutan (7, 8, 9, 10, ...) mulai dari jam_ke
        // milik jurnal ini, selama masih nyambung tanpa jeda.
        $jamKeTerakhir = (int) $jurnal->jam_ke;

        foreach ($jadwalHariItu as $jadwal) {
            if ((int) $jadwal->jam_ke === $jamKeTerakhir + 1) {
                $jamKeTerakhir = (int) $jadwal->jam_ke;
            }
        }

        return $jamKeTerakhir;
    }

    private function jamSelesaiBlokTerakhir(Jurnal $jurnal): ?string
    {
        $hari = $this->namaHariIndonesia($jurnal->tanggal);

        if (!$hari) {
            return null;
        }

        $jamKeTerakhir = $this->jamKeTerakhirBlok($jurnal);

        $jadwalTerakhir = Jadwal::where('id_guru', $jurnal->id_guru)
            ->where('id_kelas', $jurnal->id_kelas)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKeTerakhir)
            ->first();

        return $jadwalTerakhir?->jam_selesai;
    }

    private function sudahSelesai(Jurnal $jurnal): bool
    {
        $jamSelesaiTerakhir = $this->jamSelesaiBlokTerakhir($jurnal);

        // Kalau jadwalnya tidak ditemukan, izinkan tetap dikonfirmasi
        // supaya jurnal tidak "nyangkut" karena data jadwal tidak lengkap.
        if (!$jamSelesaiTerakhir || !$jurnal->tanggal) {
            return true;
        }

        $batasSelesai = Carbon::parse(
            $jurnal->tanggal->format('Y-m-d') . ' ' . $jamSelesaiTerakhir
        );

        return now()->greaterThanOrEqualTo($batasSelesai);
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR JURNAL KELAS INI YANG SIAP DIKONFIRMASI
    |--------------------------------------------------------------------------
    */

    public function getMenungguProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->where('status_validasi', 'Divalidasi') // <-- wajib sudah divalidasi guru piket
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get()
            ->filter(fn ($jurnal) => $this->sudahSelesai($jurnal))
            ->values();
    }

    public function getBelumSelesaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->where('status_validasi', 'Divalidasi') // <-- sudah divalidasi, tapi jam belum selesai
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get()
            ->reject(fn ($jurnal) => $this->sudahSelesai($jurnal))
            ->values();
    }

    /**
     * Jurnal yang jam pelajarannya mungkin sudah selesai, tapi belum
     * boleh dikonfirmasi sekretaris karena guru piket belum memvalidasi
     * jurnal tersebut sama sekali (masih 'Menunggu' di sisi guru piket).
     * Jurnal yang ditolak guru piket ('Ditolak') sengaja tidak
     * ditampilkan di sini karena itu tanggung jawab guru untuk
     * memperbaiki & mengirim ulang, bukan urusan sekretaris.
     */
    public function getMenungguValidasiGuruPiketProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->where('status_validasi', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get();
    }

    public function getRiwayatProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->whereIn('status_konfirmasi_sekretaris', ['Sesuai', 'Tidak Sesuai'])
            ->with(['guru', 'kelas'])
            ->orderByDesc('waktu_konfirmasi_sekretaris')
            ->limit(20)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | STATISTIK
    |--------------------------------------------------------------------------
    */

    public function getJumlahMenungguProperty()
    {
        return $this->menunggu->count();
    }

    public function getJumlahSesuaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Sesuai')
            ->count();
    }

    public function getJumlahTidakSesuaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Tidak Sesuai')
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | PERIKSA / TUTUP DETAIL
    |--------------------------------------------------------------------------
    */

    public function periksa($id)
    {
        $jurnal = Jurnal::with(['guru', 'kelas'])->findOrFail($id);

        // Pastikan jurnal ini memang milik kelasnya sendiri
        if ((int) $jurnal->id_kelas !== (int) session('id_kelas')) {
            return;
        }

        // Belum divalidasi guru piket → sekretaris belum boleh memproses
        if ($jurnal->status_validasi !== 'Divalidasi') {
            session()->flash(
                'error',
                'Jurnal ini belum divalidasi guru piket, belum bisa dikonfirmasi.'
            );
            return;
        }

        // Jam pelajaran (blok terakhir) belum selesai
        if (!$this->sudahSelesai($jurnal)) {
            session()->flash(
                'error',
                'Jam pelajaran guru ini belum selesai, belum bisa dikonfirmasi.'
            );
            return;
        }

        $this->jurnalTerpilih = $jurnal;
        $this->catatanSekretaris = '';
        $this->resetErrorBag();
    }

    public function tutupDetail()
    {
        $this->jurnalTerpilih = null;
        $this->catatanSekretaris = '';
        $this->resetErrorBag();
    }

    /*
    |--------------------------------------------------------------------------
    | KONFIRMASI KEHADIRAN GURU
    |--------------------------------------------------------------------------
    */

    public function konfirmasiSesuai()
    {
        $this->simpanKonfirmasi('Sesuai');
    }

    public function konfirmasiTidakSesuai()
    {
        if (trim($this->catatanSekretaris) === '') {
            $this->addError(
                'catatanSekretaris',
                'Catatan wajib diisi jika kehadiran guru dinyatakan tidak sesuai.'
            );

            return;
        }

        $this->simpanKonfirmasi('Tidak Sesuai');
    }

    private function simpanKonfirmasi(string $status)
    {
        if (!$this->jurnalTerpilih) {
            return;
        }

        $this->jurnalTerpilih->update([
            'status_konfirmasi_sekretaris' => $status,
            'id_sekretaris' => session('id_pengguna'),
            'waktu_konfirmasi_sekretaris' => now(),
            'catatan_sekretaris' => $this->catatanSekretaris ?: null,
        ]);

        $this->jurnalTerpilih = null;
        $this->catatanSekretaris = '';

        session()->flash(
            'success',
            $status === 'Sesuai'
                ? 'Kehadiran guru dikonfirmasi sesuai.'
                : 'Kehadiran guru ditandai tidak sesuai.'
        );
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
            Konfirmasi Kehadiran Guru
        </h2>

        <p style="color: #666; margin: 0;">
            Sekretaris Kelas — {{ session('nama') }}
        </p>

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


    {{-- PESAN ERROR --}}
    @if (session()->has('error'))

        <div style="
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        ">
            ✕ {{ session('error') }}
        </div>

    @endif


    {{-- STATISTIK --}}
    <div style="
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    ">

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #ddd;">
            <div style="color: #777;">Menunggu Konfirmasi</div>
            <h1 style="margin: 10px 0;">{{ $this->jumlahMenunggu }}</h1>
            <small>Jam pelajaran sudah selesai</small>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #ddd;">
            <div style="color: #777;">Sesuai</div>
            <h1 style="margin: 10px 0;">{{ $this->jumlahSesuai }}</h1>
            <small>Guru hadir langsung di kelas</small>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #ddd;">
            <div style="color: #777;">Tidak Sesuai</div>
            <h1 style="margin: 10px 0;">{{ $this->jumlahTidakSesuai }}</h1>
            <small>Perlu ditindaklanjuti</small>
        </div>

    </div>


    {{-- MENUNGGU VALIDASI GURU PIKET --}}
    @if ($this->menungguValidasiGuruPiket->count())

        <div style="background: white; border-radius: 12px; border: 1px solid #ddd; overflow: hidden; margin-bottom: 25px;">

            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0;">📋 Menunggu Validasi Guru Piket</h3>
                <p style="margin: 5px 0 0; color: #777;">
                    Jurnal ini harus divalidasi guru piket terlebih dahulu sebelum bisa dikonfirmasi sekretaris.
                </p>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                            <th style="padding: 12px; text-align: left;">Guru</th>
                            <th style="padding: 12px; text-align: left;">Jam</th>
                            <th style="padding: 12px; text-align: left;">Materi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->menungguValidasiGuruPiket as $jurnal)
                            <tr style="border-top: 1px solid #eee; color: #999;">
                                <td style="padding: 12px;">
                                    {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                                </td>
                                <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                                <td style="padding: 12px;">Jam {{ $jurnal->jam_ke }}</td>
                                <td style="padding: 12px;">{{ $jurnal->materi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

    @endif


    {{-- DAFTAR JURNAL SIAP DIKONFIRMASI --}}
    <div style="background: white; border-radius: 12px; border: 1px solid #ddd; overflow: hidden; margin-bottom: 25px;">

        <div style="padding: 20px; border-bottom: 1px solid #ddd;">
            <h3 style="margin: 0;">🔔 Perlu Dikonfirmasi</h3>
            <p style="margin: 5px 0 0; color: #777;">
                Jurnal di kelas ini yang sudah divalidasi guru piket dan jam pelajarannya sudah selesai.
            </p>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left;">No</th>
                        <th style="padding: 12px; text-align: left;">Tanggal</th>
                        <th style="padding: 12px; text-align: left;">Guru</th>
                        <th style="padding: 12px; text-align: left;">Jam</th>
                        <th style="padding: 12px; text-align: left;">Materi</th>
                        <th style="padding: 12px; text-align: center;">Status Guru</th>
                        <th style="padding: 12px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->menunggu as $index => $jurnal)
                        <tr style="border-top: 1px solid #eee;">
                            <td style="padding: 12px;">{{ $index + 1 }}</td>
                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                            <td style="padding: 12px;">
                                @php $jamAkhir = $this->jamKeTerakhirBlok($jurnal); @endphp
                                Jam {{ $jurnal->jam_ke }}
                                @if ($jamAkhir > $jurnal->jam_ke)
                                    <br><small style="color:#777;">s/d jam {{ $jamAkhir }}</small>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->materi }}</td>
                            <td style="padding: 12px; text-align: center;">
                                {{ $jurnal->status_kehadiran_guru }}
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <button wire:click="periksa({{ $jurnal->id_jurnal }})" style="
                                    padding: 8px 14px;
                                    border: none;
                                    border-radius: 8px;
                                    background: #2563eb;
                                    color: white;
                                    cursor: pointer;
                                ">
                                    Periksa
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #777;">
                                ✓ Tidak ada jurnal yang perlu dikonfirmasi saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>


    {{-- MENUNGGU JAM SELESAI --}}
    @if ($this->belumSelesai->count())

        <div style="background: white; border-radius: 12px; border: 1px solid #ddd; overflow: hidden; margin-bottom: 25px;">

            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0;">⏳ Masih Berlangsung</h3>
                <p style="margin: 5px 0 0; color: #777;">
                    Jurnal ini baru bisa dikonfirmasi setelah jam pelajarannya selesai.
                </p>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                            <th style="padding: 12px; text-align: left;">Guru</th>
                            <th style="padding: 12px; text-align: left;">Jam</th>
                            <th style="padding: 12px; text-align: left;">Materi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->belumSelesai as $jurnal)
                            <tr style="border-top: 1px solid #eee; color: #999;">
                                <td style="padding: 12px;">
                                    {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                                </td>
                                <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                                <td style="padding: 12px;">
                                    @php $jamAkhir = $this->jamKeTerakhirBlok($jurnal); @endphp
                                    Jam {{ $jurnal->jam_ke }}
                                    @if ($jamAkhir > $jurnal->jam_ke)
                                        <br><small>menunggu s/d jam {{ $jamAkhir }} selesai</small>
                                    @endif
                                </td>
                                <td style="padding: 12px;">{{ $jurnal->materi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

    @endif


    {{-- DETAIL & FORM KONFIRMASI --}}
    @if ($jurnalTerpilih)

        <div style="background: white; border-radius: 12px; border: 1px solid #ddd; overflow: hidden; margin-bottom: 25px;">

            <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0;">Detail Jurnal</h3>
                    <p style="margin: 5px 0 0; color: #777;">
                        Konfirmasi apakah guru benar-benar hadir langsung di kelas.
                    </p>
                </div>
                <button wire:click="tutupDetail" style="
                    border: none; background: #eee; padding: 8px 12px;
                    border-radius: 8px; cursor: pointer;
                ">✕ Tutup</button>
            </div>

            <div style="padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div>
                    <strong>Guru</strong>
                    <div style="margin-top: 5px;">{{ $jurnalTerpilih->guru->nama ?? '-' }}</div>
                </div>
                <div>
                    <strong>Tanggal</strong>
                    <div style="margin-top: 5px;">
                        {{ \Carbon\Carbon::parse($jurnalTerpilih->tanggal)->format('d F Y') }}
                    </div>
                </div>
                <div>
                    <strong>Jam Ke</strong>
                    <div style="margin-top: 5px;">Jam {{ $jurnalTerpilih->jam_ke }}</div>
                </div>
                <div>
                    <strong>Status Kehadiran (lapor guru)</strong>
                    <div style="margin-top: 5px;">{{ $jurnalTerpilih->status_kehadiran_guru }}</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <strong>Materi</strong>
                    <div style="margin-top: 5px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        {{ $jurnalTerpilih->materi }}
                    </div>
                </div>
            </div>

            <div style="padding: 20px; border-top: 1px solid #eee;">
                <h3 style="margin-top: 0;">Konfirmasi Kehadiran</h3>

                <label>Catatan (wajib jika "Tidak Sesuai")</label>
                <textarea
                    wire:model="catatanSekretaris"
                    rows="3"
                    placeholder="Contoh: guru tidak masuk kelas, hanya memberi tugas lewat WA, dsb."
                    style="width: 100%; margin-top: 8px; padding: 12px; border: 1px solid #ccc; border-radius: 8px; resize: vertical; box-sizing: border-box;"
                ></textarea>

                @error('catatanSekretaris')
                    <div style="color: #dc2626; margin-top: 5px;">{{ $message }}</div>
                @enderror

                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button wire:click="tutupDetail" style="
                        padding: 10px 18px; border: 1px solid #ccc; background: white;
                        border-radius: 8px; cursor: pointer;
                    ">Batal</button>

                    <button wire:click="konfirmasiTidakSesuai" style="
                        padding: 10px 18px; border: none; background: #dc2626;
                        color: white; border-radius: 8px; cursor: pointer;
                    ">✕ Tidak Sesuai</button>

                    <button wire:click="konfirmasiSesuai" style="
                        padding: 10px 18px; border: none; background: #16a34a;
                        color: white; border-radius: 8px; cursor: pointer;
                    ">✓ Sesuai, Guru Hadir</button>
                </div>
            </div>

        </div>

    @endif


    {{-- RIWAYAT --}}
    <div style="background: white; border-radius: 12px; border: 1px solid #ddd; overflow: hidden;">

        <div style="padding: 20px; border-bottom: 1px solid #ddd;">
            <h3 style="margin: 0;">Riwayat Konfirmasi</h3>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left;">Tanggal</th>
                        <th style="padding: 12px; text-align: left;">Guru</th>
                        <th style="padding: 12px; text-align: left;">Jam</th>
                        <th style="padding: 12px; text-align: center;">Hasil</th>
                        <th style="padding: 12px; text-align: left;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->riwayat as $jurnal)
                        <tr style="border-top: 1px solid #eee;">
                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                            <td style="padding: 12px;">Jam {{ $jurnal->jam_ke }}</td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="
                                    display: inline-block; padding: 5px 10px; border-radius: 20px;
                                    font-size: 13px;
                                    background: {{ $jurnal->status_konfirmasi_sekretaris === 'Sesuai' ? '#dcfce7' : '#fee2e2' }};
                                    color: {{ $jurnal->status_konfirmasi_sekretaris === 'Sesuai' ? '#166534' : '#991b1b' }};
                                ">
                                    {{ $jurnal->status_konfirmasi_sekretaris }}
                                </span>
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->catatan_sekretaris ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #777;">
                                Belum ada riwayat konfirmasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</div>