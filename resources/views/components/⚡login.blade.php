<?php

use Livewire\Component;
use App\Models\Pengguna;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public string $nip = '';
public string $password = '';
public string $error = '';
public bool $showForgot = false;

    public function login()
    {
        $this->error = '';

        $user = Pengguna::where('nip', trim($this->nip))
            ->where('password', $this->password)
            ->first();

        if (!$user) {
            $this->error = 'NIP/ID atau password salah. Silakan periksa kembali.';
            return;
        }

        Session::put([
            'id_pengguna' => $user->id_pengguna,
            'nama' => $user->nama,
            'nip' => $user->nip,
            'role' => $user->role,
            'status_kepegawaian' => $user->status_kepegawaian,
            'mapel_diampu' => $user->mapel_diampu,
            'id_kelas' => $user->id_kelas,
        ]);

        if ($user->role === 'guru_piket') {
    return redirect()->route('guru-piket');
}
        return redirect()->route('dashboard');
    }
};
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIJAGA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>

<body>

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


                <form wire:submit="login">

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
                            autofocus
                        >

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
                            required
                        >

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


                <div class="mt-3 p-3"
                     style="background:#f3f6fb;
                            border-radius:10px;
                            border:1px solid var(--border);">

                    <div class="fw-bold text-muted mb-2"
                         style="font-size:11px;
                                text-transform:uppercase;
                                letter-spacing:.04em;">

                        Demo — Akun Tersedia

                    </div>

                    <div style="font-size:12px;
                                color:var(--muted);
                                line-height:1.9;">

                        <strong style="color:var(--text);">
    Admin:
</strong>
NIP
<code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">
    19800101200001001
</code>
<br>

<strong style="color:var(--text);">
    Guru:
</strong>
NIP
<code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">
    19850101201001001
</code>
<br>

<strong style="color:var(--text);">
    Sekretaris (X RPL 1):
</strong>
ID
<code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">
    2601001
</code>
<br>

<strong style="color:var(--text);">
    Password semua akun:
</strong>
<code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">
    guru123
</code>

                    </div>

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

</body>
</html>