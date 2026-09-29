<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('assets/logo-sijaga.png') }}">

    <title>SIJAGA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">

    @if (request()->query('print'))
    <style>
        body:has(.print-report) {
            min-height: 100vh;
            background: linear-gradient(145deg, #eaf1fb, #f7f9fd 55%, #e9f0fa);
        }

        .print-shell {
            min-height: 100vh;
            padding: 36px 20px;
        }

        .print-frame {
            width: min(100%, 1180px);
            min-height: calc(100vh - 72px);
            margin: 0 auto;
            padding: 32px;
            border: 1px solid #dce6f5;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(31, 57, 91, .14);
        }

        @media (max-width: 767.98px) {
            .print-shell {
                padding: 12px;
            }

            .print-frame {
                min-height: calc(100vh - 24px);
                padding: 16px;
                border-radius: 13px;
            }
        }

        @media print {
            body:has(.print-report) {
                min-height: 0;
                background: #fff;
            }

            .print-shell {
                min-height: 0;
                padding: 0;
            }

            .print-frame {
                width: 100%;
                min-height: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
    @endif

    @livewireStyles
</head>

<body>

    @if (request()->query('print'))
    <main class="print-shell">
        <div class="print-frame">
            {{ $slot }}
        </div>
    </main>
    @else
    <div class="app-wrapper">

        {{-- TOPBAR MOBILE --}}
        <div class="topbar-mobile d-lg-none">
            <button class="btn-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarNav">
                &#9776;
            </button>
            <div class="sidebar-logo">
                <img src="{{ asset('assets/logo-sijaga.png') }}" alt="Logo SIJAGA">
            </div>
            <div class="brand-mini">
                SIJAGA
            </div>
            <div class="layout-clock layout-clock-mobile" aria-label="Jam dan tanggal saat ini">
                <time class="layout-clock-time" data-layout-clock-time></time>
                <time class="layout-clock-date" data-layout-clock-date></time>
            </div>
        </div>

        {{-- SIDEBAR --}}
        <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarNav">

            {{-- BRAND --}}
            <div class="sidebar-brand d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-logo">
                        <img src="{{ asset('assets/logo-sijaga.png') }}" alt="Logo SIJAGA">
                    </div>
                    <div>
                        <div class="text-white fw-bold" style="font-size:12px; line-height:1.2;">
                            SIJAGA
                        </div>
                        <div style="color:rgba(255,255,255,.5); font-size:10px; line-height:1.3;">
                            Sistem Jurnal &amp; Absensi
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                    aria-label="Close"></button>
            </div>

            <div class="layout-clock layout-clock-desktop" aria-label="Jam dan tanggal saat ini">
                <time class="layout-clock-time" data-layout-clock-time></time>
                <time class="layout-clock-date" data-layout-clock-date></time>
            </div>

{{-- NAVIGASI --}}
            <nav class="sidebar-nav">
                @php
                $role = session('role', 'guru');
                $navItems = [];

                // Satu sumber kebenaran untuk status piket: hitung langsung via service
                // agar selalu mengikuti jadwal hari ini, tidak basi karena session login.
                $isGuruPiket = ($role === 'guru' && session('id_pengguna'))
                    ? app(\App\Services\GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'))
                    : false;

                if ($role === 'admin') {
                $navItems = [
                'admin' => ['label' => 'Dashboard Admin', 'route' => 'admin'],
                'pengguna' => ['label' => 'Pengguna', 'route' => 'admin', 'anchor' =>
                'pengguna'],
                'guru' => ['label' => 'Guru', 'route' => 'admin', 'anchor' => 'guru'],
                'siswa' => ['label' => 'Siswa', 'route' => 'admin', 'anchor' => 'siswa'],
                'kode' => ['label' => 'Kelas', 'route' => 'admin', 'anchor' => 'kode'],
                'jadwal' => ['label' => 'Jadwal Mengajar', 'route' => 'admin', 'anchor' =>
                'jadwal'],
                'jadwal_piket' => ['label' => 'Jadwal Piket', 'route' => 'admin', 'anchor' =>
                'jadwal-piket'],
                'jurnal' => ['label' => 'Jurnal', 'route' => 'admin', 'anchor' => 'jurnal'],
                'dispensasi' => ['label' => 'Dispensasi', 'route' => 'admin', 'anchor' =>
                'dispensasi'],
                ];
                } elseif ($role === 'wakasek') {
                $navItems = [
                'wakasek' => ['label' => 'Dashboard', 'route' => 'wakasek'],
                'monitoring_guru' => ['label' => 'Monitoring Guru', 'route' => 'wakasek',
                'anchor' => 'monitoring-guru'],
                'monitoring_jurnal' => ['label' => 'Monitoring Jurnal', 'route' => 'wakasek',
                'anchor' => 'monitoring-jurnal'],
                'pengajuan_izin' => ['label' => 'Pengajuan Izin Guru', 'route' => 'wakasek',
                'anchor' => 'pengajuan-izin'],
                'dispensasi' => ['label' => 'Dispensasi', 'route' => 'wakasek', 'anchor' =>
                'dispensasi'],
                'rekap' => ['label' => 'Rekap', 'route' => 'wakasek', 'anchor' => 'rekap'],
                ];
                } elseif ($role === 'guru') {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard'],
                'jadwal_saya' => ['label' => 'Jadwal Saya', 'route' => 'dashboard', 'anchor' =>
                'jadwal-saya'],
                'input_jurnal' => ['label' => 'Input Jurnal', 'route' => 'input-jurnal'],
                'riwayat' => ['label' => 'Riwayat Saya', 'route' => 'riwayat'],
                'notifikasi' => ['label' => 'Notifikasi', 'route' => 'notifikasi'],
                ];

                if ($isGuruPiket) {
                $navItems['dispensasi'] = ['label' => 'Izin & Dispensasi', 'route' =>
                'dispensasi'];
                $navItems['guru_piket'] = ['label' => 'Piket Hari Ini', 'route' => 'guru-piket'];
                $navItems['rekap_dispensasi'] = ['label' => 'Rekapan', 'route' =>
                'rekap-dispensasi'];
                }
                } elseif ($role === 'sekretaris') {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'route' => 'sekretaris', 'anchor' =>
                'dashboard'],
                'data_kelas' => ['label' => 'Data Kelas', 'route' => 'sekretaris', 'anchor' =>
                'data-kelas'],
                'validasi_jurnal' => ['label' => 'Validasi Jurnal', 'route' => 'sekretaris', 'anchor' =>
                'validasi-jurnal'],
                'tugas_guru' => ['label' => 'Tugas Guru Tidak Hadir', 'route' => 'sekretaris', 'anchor' =>
                'tugas-guru'],
                'kehadiran' => ['label' => 'Kehadiran', 'route' => 'sekretaris', 'anchor' =>
                'kehadiran'],
                'surat_dispensasi' => ['label' => 'Surat Dispensasi', 'route' => 'sekretaris', 'anchor' =>
                'surat-dispensasi'],
                'rekap' => ['label' => 'Rekap', 'route' => 'sekretaris', 'anchor' => 'rekap'],
                ];
                } else {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard'],
                'riwayat' => ['label' => 'Laporan Tervalidasi', 'route' => 'riwayat'],
                'data_master' => ['label' => 'Data Master', 'route' => 'data-master'],
                ];
                }
                @endphp

                @foreach ($navItems as $key => $item)
                @php
                $isActive = request()->routeIs($item['route']) && empty($item['anchor']);
                if (session('role') === 'sekretaris' && request()->routeIs('sekretaris')) {
                $activeSection = request()->query('menu', 'dashboard');
                $isActive = ($item['anchor'] ?? '') === $activeSection;
                }
                @endphp

                <a href="{{ Route::has($item['route']) ? route($item['route']).(session('role') === 'sekretaris' && !empty($item['anchor']) ? '?menu='.$item['anchor'] : (!empty($item['anchor']) ? '#'.$item['anchor'] : '')) : '#' }}"
                    class="nav-link {{ $isActive ? 'active' : '' }}" data-nav-anchor="{{ $item['anchor'] ?? '' }}"
                    data-route-active="{{ $isActive ? 'true' : 'false' }}">

                    <span aria-hidden="true" style="font-size:15px; width:20px; text-align:center;"></span>

                    {{ $item['label'] }}

                    <span class="dot" @if (!$isActive) hidden @endif></span>
                </a>
                @endforeach
            </nav>

            {{-- USER --}}
            <div class="sidebar-user">
                <div class="profile-box">
                    @php
                    $nama = session('nama', 'Pengguna');
                    $parts = explode(' ', trim($nama));
                    $initials = '';

                    foreach (array_slice($parts, 0, 2) as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                    }
                    @endphp

                    <div class="avatar-circle">
                        {{ $initials }}
                    </div>

                    <div style="flex:1; min-width:0;">
                        <div class="text-white fw-semibold text-truncate" style="font-size:12px;">
                            {{ explode(',', $nama)[0] }}
                        </div>

                        <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                            @if(session('status_kepegawaian'))
                            <span class="badge-status" style="font-size:9px; padding:1px 5px;">
                                {{ session('status_kepegawaian') }}
                            </span>
                            @endif

                            @if(session('role') && session('role') !== 'guru')
                            <span class="badge-status" style="font-size:9px; padding:1px 5px;">
                                {{ strtoupper(session('role')) }}
                            </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- LOGOUT --}}
                <a href="{{ route('logout') }}" class="btn-logout">
                    <span>&#8592;</span>
                    Keluar
                </a>
            </div>

        </aside>

        {{-- CONTENT --}}
        <div class="content-col" style="flex:1; min-width:0;">
            <main class="main-content">
                {{ $slot }}
            </main>
        </div>

    </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    @livewireScripts

    <script>
        (() => {
            const timeElements = document.querySelectorAll('[data-layout-clock-time]');
            const dateElements = document.querySelectorAll('[data-layout-clock-date]');
            const locale = 'id-ID';
            const timeOptions = {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            };
            const dateOptions = {
                timeZone: 'Asia/Jakarta',
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            };

            const updateClock = () => {
                const now = new Date();

                timeElements.forEach((element) => {
                    element.textContent = new Intl.DateTimeFormat(locale, timeOptions).format(now);
                });

                dateElements.forEach((element) => {
                    element.textContent = new Intl.DateTimeFormat(locale, dateOptions).format(now);
                });
            };

            updateClock();
            window.setInterval(updateClock, 1000);
        })();

        (() => {
            const nav = document.querySelector('.sidebar-nav');

            if (!nav) {
                return;
            }

            const links = [...nav.querySelectorAll('.nav-link')];

            const syncActiveLink = () => {
                const currentHash = decodeURIComponent(window.location.hash.slice(1));
                const hashLink = currentHash ?
                    links.find((link) => link.dataset.navAnchor === currentHash) :
                    null;
                const activeLink = hashLink ?? links.find((link) => link.dataset.routeActive === 'true');

                links.forEach((link) => {
                    const isActive = link === activeLink;

                    link.classList.toggle('active', isActive);
                    const dot = link.querySelector('.dot');

                    if (dot) {
                        dot.hidden = !isActive;
                    }
                });
            };

            syncActiveLink();
            window.addEventListener('hashchange', syncActiveLink);
            window.addEventListener('pageshow', syncActiveLink);
            document.addEventListener('livewire:navigated', syncActiveLink);
            document.addEventListener('livewire:init', () => {
                Livewire.on('sekretaris-menu-berubah', ({
                    section
                }) => {
                    links.forEach((link) => {
                        const isActive = link.dataset.navAnchor === section;

                        link.classList.toggle('active', isActive);
                        link.dataset.routeActive = isActive ? 'true' : 'false';

                        const dot = link.querySelector('.dot');

                        if (dot) {
                            dot.hidden = !isActive;
                        }
                    });
                });
            });
        })();
    </script>

</body>

</html>
