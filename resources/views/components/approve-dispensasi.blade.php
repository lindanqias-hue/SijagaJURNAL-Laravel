<?php

use Livewire\Component;
use App\Models\Dispensasi;
use App\Models\Pengguna;
use Carbon\Carbon;

new class extends Component
{
    public $token;

    public $dispensasi;

    public $wakasekList = [];

    public $id_wakasek = '';
    public $catatan_wakasek = '';

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    | $token otomatis diisi Livewire dari parameter route {token}
    | karena nama parameter route dan nama argumen mount() sama.
    */
    public function mount($token)
    {
        $this->token = $token;

        // Kalau token tidak ditemukan -> otomatis 404
        $this->dispensasi = Dispensasi::where('token', $token)->firstOrFail();

        $this->wakasekList = Pengguna::where('role', 'wakasek')
            ->orderBy('nama')
            ->get();
    }

    private function muatUlangDispensasi()
    {
        $this->dispensasi = $this->dispensasi->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | SETUJUI
    |--------------------------------------------------------------------------
    */
    public function setujui()
    {
        $this->validate([
            'id_wakasek'      => 'required|exists:pengguna,id_pengguna',
            'catatan_wakasek' => 'nullable|string|max:500',
        ], [
            'id_wakasek.required' => 'Pilih nama Anda terlebih dahulu.',
        ]);

        if ($this->dispensasi->status !== 'Menunggu Persetujuan') {
            session()->flash('error', 'Dispensasi ini sudah diproses sebelumnya.');
            $this->muatUlangDispensasi();
            return;
        }

        $this->dispensasi->update([
            'status'          => 'Disetujui',
            'id_wakasek'      => $this->id_wakasek,
            'waktu_approval'  => Carbon::now('Asia/Jakarta'),
            'catatan_wakasek' => $this->catatan_wakasek,
        ]);

        /*
        |----------------------------------------------------------------
        | TODO (Poin 4 - Integrasi Jurnal)
        |----------------------------------------------------------------
        | Panggil logic/observer di sini untuk membuat/mengupdate
        | absensi_siswa (kolom keterangan_dispensasi) sesuai jurnal
        | kelas & jam yang berlaku untuk $this->dispensasi.
        | Contoh: app(DispensasiJurnalService::class)->sync($this->dispensasi);
        */

        $this->muatUlangDispensasi();

        session()->flash('success', 'Dispensasi berhasil disetujui.');
    }

    /*
    |--------------------------------------------------------------------------
    | TOLAK
    |--------------------------------------------------------------------------
    */
    public function tolak()
    {
        $this->validate([
            'id_wakasek'      => 'required|exists:pengguna,id_pengguna',
            'catatan_wakasek' => 'required|string|max:500',
        ], [
            'id_wakasek.required'      => 'Pilih nama Anda terlebih dahulu.',
            'catatan_wakasek.required' => 'Catatan wajib diisi saat menolak.',
        ]);

        if ($this->dispensasi->status !== 'Menunggu Persetujuan') {
            session()->flash('error', 'Dispensasi ini sudah diproses sebelumnya.');
            $this->muatUlangDispensasi();
            return;
        }

        $this->dispensasi->update([
            'status'          => 'Ditolak',
            'id_wakasek'      => $this->id_wakasek,
            'waktu_approval'  => Carbon::now('Asia/Jakarta'),
            'catatan_wakasek' => $this->catatan_wakasek,
        ]);

        $this->muatUlangDispensasi();

        session()->flash('success', 'Dispensasi ditolak.');
    }
};
?>

<div style="width:100%; min-height:100vh; padding:30px; background:#f8fafc;">

    <div style="max-width:620px; margin:0 auto;">

        <h2 style="margin:0 0 5px 0; color:#111827;">Approval Dispensasi Siswa</h2>
        <p style="margin:0 0 20px 0; color:#6b7280;">
            Periksa detail dispensasi lalu berikan keputusan.
        </p>

        @if (session('success'))
            <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:8px; margin-bottom:15px;">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:15px;">
                {{ session('error') }}
            </div>
        @endif

        {{-- DETAIL DISPENSASI --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:15px;">

            <div style="margin-bottom:10px;">
                <strong>Nama Siswa:</strong> {{ $dispensasi->siswa->nama_siswa ?? '-' }}
            </div>

            <div style="margin-bottom:10px;">
                <strong>Kelas:</strong> {{ $dispensasi->kelas->nama_kelas ?? '-' }}
            </div>

            <div style="margin-bottom:10px;">
                <strong>Jenis Dispensasi:</strong> {{ $dispensasi->jenis_dispensasi }}
            </div>

            <div style="margin-bottom:10px;">
                <strong>Tanggal:</strong>
                {{ optional($dispensasi->tanggal)->translatedFormat('d F Y') }}
            </div>

            @if ($dispensasi->jenis_dispensasi === 'Per Jam')
                <div style="margin-bottom:10px;">
                    <strong>Jam ke:</strong>
                    {{ $dispensasi->jam_ke_mulai }}
                    @if ($dispensasi->jam_ke_selesai && $dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)
                        s/d {{ $dispensasi->jam_ke_selesai }}
                    @endif
                </div>
            @endif

            <div style="margin-bottom:10px;">
                <strong>Alasan:</strong> {{ $dispensasi->alasan }}
            </div>

            <div style="margin-bottom:10px;">
                <strong>Diajukan oleh (Guru Piket):</strong>
                {{ $dispensasi->guruPiket->nama ?? '-' }}
            </div>

            <div>
                <strong>Status saat ini:</strong>
                <span style="font-weight:600;">{{ $dispensasi->status }}</span>
            </div>

            @if ($dispensasi->status !== 'Menunggu Persetujuan')
                <hr style="margin:15px 0;">
                <div style="font-size:13px; color:#6b7280;">
                    Diproses oleh: {{ $dispensasi->wakasek->nama ?? '-' }}
                    pada {{ optional($dispensasi->waktu_approval)->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB
                </div>
                @if ($dispensasi->catatan_wakasek)
                    <div style="font-size:13px; color:#6b7280; margin-top:5px;">
                        Catatan: {{ $dispensasi->catatan_wakasek }}
                    </div>
                @endif
            @endif

        </div>

        {{-- FORM APPROVAL, cuma muncul kalau masih menunggu --}}
        @if ($dispensasi->status === 'Menunggu Persetujuan')
            <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">

                <label style="display:block; font-weight:600; margin-bottom:6px;">
                    Konfirmasi sebagai Wakasek
                </label>
                <select wire:model="id_wakasek"
                    style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-bottom:5px;">
                    <option value="">-- Pilih Nama Anda --</option>
                    @foreach ($wakasekList as $w)
                        <option value="{{ $w->id_pengguna }}">{{ $w->nama }}</option>
                    @endforeach
                </select>
                @error('id_wakasek')
                    <div style="color:#dc2626; font-size:13px; margin-bottom:10px;">{{ $message }}</div>
                @enderror

                <label style="display:block; font-weight:600; margin:10px 0 6px 0;">
                    Catatan (wajib diisi jika menolak)
                </label>
                <textarea wire:model="catatan_wakasek" rows="3"
                    style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;"
                    placeholder="Opsional untuk setuju, wajib untuk tolak"></textarea>
                @error('catatan_wakasek')
                    <div style="color:#dc2626; font-size:13px; margin-top:5px;">{{ $message }}</div>
                @enderror

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button wire:click="setujui" wire:confirm="Setujui dispensasi ini?"
                        style="flex:1; background:#16a34a; color:#fff; border:none; padding:10px; border-radius:6px; font-weight:600; cursor:pointer;">
                        Setujui
                    </button>
                    <button wire:click="tolak" wire:confirm="Tolak dispensasi ini?"
                        style="flex:1; background:#dc2626; color:#fff; border:none; padding:10px; border-radius:6px; font-weight:600; cursor:pointer;">
                        Tolak
                    </button>
                </div>

            </div>
        @endif

    </div>

</div>