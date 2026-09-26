<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Pengguna;
use App\Services\GuruPiketAccessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.guest')] class extends Component
{
    public string $nip = '';
    public string $password = '';
    public string $error = '';
    public bool $showForgot = false;

    public function login()
    {
        $this->error = '';

        $user = Pengguna::where('nip', trim($this->nip))->first();

        if (!$user || (!Hash::check($this->password, $user->password) && $user->password !== $this->password)) {
            $this->error = 'NIP/ID atau password salah. Silakan periksa kembali.';
            return;
        }

        $roleSistem = $user->role === 'guru_piket' ? 'guru' : $user->role;
        $ditugaskanSebagaiGuruPiket = app(GuruPiketAccessService::class)
            ->bertugasHariIni($user->id_pengguna);

        Session::put([
            'id_pengguna' => $user->id_pengguna,
            'nama' => $user->nama,
            'nip' => $user->nip,
            'role' => $roleSistem,
            'status_kepegawaian' => $user->status_kepegawaian,
            'mapel_diampu' => $user->mapel_diampu,
            'id_kelas' => $user->id_kelas,
            'is_guru_piket' => $ditugaskanSebagaiGuruPiket,
        ]);

        if ($user->role === 'sekretaris') {
            return redirect()->route('sekretaris');
        }

        if ($roleSistem === 'admin') {
            return redirect()->route('admin');
        }

        if ($roleSistem === 'wakasek') {
            return redirect()->route('wakasek');
        }

        return redirect()->route('dashboard');
    }
};
?>

<div>

    <div class="login-wrapper">

        <div class="deco-circle"
            style="top:-120px; right:-120px; width:400px; height:400px;">
        </div>

        <div class="deco-circle"
            style="top:-60px; right:-60px; width:240px; height:240px;">
        </div>

        <div class="deco-circle"
            style="bottom:-100px; left:-100px; width:360px; height:360px;">
        </div>


        <div style="width:100%; max-width:440px; position:relative; z-index:1;">

            <div class="login-card-header">

                <div class="login-logo">🏫</div>

                <div class="fw-bold"
                    style="font-size:21px; color:#fff;">
                    Sistem Informasi
                </div>

                <div class="fw-bold"
                    style="font-size:21px; color:#60a5fa;">
                    Jurnal &amp; Absensi Guru
                </div>

                <div style="color:rgba(255,255,255,.45);
                        font-size:12px;
                        margin-top:8px;">
                    SMK Negeri 1 Contoh — Tahun Ajaran 2026/2027
                </div>

            </div>


            <div class="login-card-body">

                @if (!$showForgot)

                <div class="fw-bold" style="font-size:16px;">
                    Masuk ke Sistem
                </div>

                <div class="text-muted mb-4" style="font-size:13px;">
                    Gunakan NIP/ID dan password yang terdaftar.
                </div>


                <form wire:submit.prevent="login">

                    <div class="mb-3">

                        <label class="form-label-sm">
                            NIP / ID Pengguna
                        </label>

                        <input
                            type="text"
                            wire:model="nip"
                            class="form-control-custom"
                            placeholder="Masukkan NIP atau ID Anda"
                            required
                            autofocus>

                    </div>

                    <div class="mb-3">

                        <label class="form-label-sm">
                            Password
                        </label>

                        <input
                            type="password"
                            wire:model="password"
                            class="form-control-custom"
                            placeholder="Masukkan password"
                            required>

                    </div>


                    @if ($error)

                    <div class="mb-3 alert-box alert-danger-box">
                        ⚠️ {{ $error }}
                    </div>

                    @endif


                    <button
                        type="submit"
                        class="btn btn-primary-gradient">

                        Masuk Sistem

                    </button>

                </form>


                <div class="text-center mt-3">

                    <button
                        type="button"
                        wire:click="$set('showForgot', true)"
                        style="font-size:13px;
                               text-decoration:underline;
                               border:0;
                               background:none;
                               padding:0;">

                        Lupa Password?

                    </button>

                </div>


                @else


                <div class="text-center mb-4">

                    <div style="font-size:38px; margin-bottom:12px;">
                        🔑
                    </div>

                    <div class="fw-bold" style="font-size:16px;">
                        Lupa Password?
                    </div>

                    <div class="text-muted mt-2"
                        style="font-size:13px; line-height:1.6;">

                        Silakan hubungi Administrator Sekolah
                        untuk mereset password Anda.

                    </div>

                </div>


                <div class="p-3 mb-4"
                    style="background:#f3f6fb;
                            border-radius:10px;
                            border:1px solid var(--border);">

                    <div class="fw-bold mb-1"
                        style="font-size:12px;">

                        Kontak Admin Sekolah

                    </div>

                    <div style="font-size:13px; color:var(--muted);">
                        📞 (021) 1234-5678
                    </div>

                    <div style="font-size:13px; color:var(--muted);">
                        ✉️ admin@smkn1contoh.sch.id
                    </div>

                </div>


                <button
                    type="button"
                    wire:click="$set('showForgot', false)"
                    class="btn w-100"
                    style="padding:12px;
                           background:var(--bg);
                           color:var(--text);
                           border:1.5px solid var(--border);
                           border-radius:10px;
                           font-weight:700;
                           font-size:14px;">

                    ← Kembali ke Login

                </button>


                @endif

            </div>

        </div>

    </div>

</div>