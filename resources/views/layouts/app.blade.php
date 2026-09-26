<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
            <div class="sidebar-logo">&#127979;</div>
            <div class="brand-mini">
                SIJAGA
            </div>
        </div>

        <div class="layout-clock layout-clock-mobile" aria-label="Jam dan tanggal saat ini">
            <time class="layout-clock-time" data-layout-clock-time></time>
            <time class="layout-clock-date" data-layout-clock-date></time>
        </div>

        {{-- SIDEBAR --}}
        <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarNav">

            {{-- BRAND --}}
            <div class="sidebar-brand d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-logo">
                        &#127979;
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

                if ($role === 'admin') {
                $navItems = [
                'admin' => ['label' => 'Dashboard Admin', 'icon' => '&#9881;', 'route' => 'admin'],
                'pengguna' => ['label' => 'Pengguna', 'icon' => '&#128100;', 'route' => 'admin', 'anchor' =>
                'pengguna'],
                'guru' => ['label' => 'Guru', 'icon' => '&#127979;', 'route' => 'admin', 'anchor' => 'guru'],
                'siswa' => ['label' => 'Siswa', 'icon' => '&#128101;', 'route' => 'admin', 'anchor' => 'siswa'],
                'kelas' => ['label' => 'Kelas', 'icon' => '&#127891;', 'route' => 'admin', 'anchor' => 'kelas'],
                'jadwal' => ['label' => 'Jadwal Mengajar', 'icon' => '&#128197;', 'route' => 'admin', 'anchor' =>
                'jadwal'],
                'jadwal_piket' => ['label' => 'Jadwal Piket', 'icon' => '&#128101;', 'route' => 'admin', 'anchor' =>
                'jadwal-piket'],
                'jurnal' => ['label' => 'Jurnal', 'icon' => '&#128203;', 'route' => 'admin', 'anchor' => 'jurnal'],
                'dispensasi' => ['label' => 'Dispensasi', 'icon' => '&#128221;', 'route' => 'admin', 'anchor' =>
                'dispensasi'],
                ];
                } elseif ($role === 'wakasek') {
                $navItems = [
                'wakasek' => ['label' => 'Dashboard', 'icon' => '&#128202;', 'route' => 'wakasek'],
                'monitoring_guru' => ['label' => 'Monitoring Guru', 'icon' => '&#128100;', 'route' => 'wakasek',
                'anchor' => 'monitoring-guru'],
                'monitoring_jurnal' => ['label' => 'Monitoring Jurnal', 'icon' => '&#128203;', 'route' => 'wakasek',
                'anchor' => 'monitoring-jurnal'],
                'dispensasi' => ['label' => 'Dispensasi', 'icon' => '&#128221;', 'route' => 'wakasek', 'anchor' =>
                'dispensasi'],
                'rekap' => ['label' => 'Rekap', 'icon' => '&#128202;', 'route' => 'rekap-dispensasi'],
                ];
                } elseif ($role === 'guru') {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => '&#8862;', 'route' => 'dashboard'],
                'jadwal_saya' => ['label' => 'Jadwal Saya', 'icon' => '&#128197;', 'route' => 'dashboard', 'anchor' =>
                'jadwal-saya'],
                'input_jurnal' => ['label' => 'Input Jurnal', 'icon' => '&#9998;', 'route' => 'input-jurnal'],
                'riwayat' => ['label' => 'Riwayat Saya', 'icon' => '&#128203;', 'route' => 'riwayat'],
                'notifikasi' => ['label' => 'Notifikasi', 'icon' => '&#128276;', 'route' => 'notifikasi'],
                'dispensasi' => ['label' => 'Dispensasi', 'icon' => '&#128221;', 'route' => 'dispensasi'],
                ];

                if (session('is_guru_piket')) {
                $navItems['guru_piket'] = ['label' => 'Piket Hari Ini', 'icon' => '&#128101;', 'route' => 'guru-piket'];
                $navItems['dispensasi'] = ['label' => 'Izin & Dispensasi', 'icon' => '&#128221;', 'route' =>
                'dispensasi'];
                $navItems['rekap_dispensasi'] = ['label' => 'Rekap Dispensasi', 'icon' => '&#128202;', 'route' =>
                'rekap-dispensasi'];
                }
                } elseif ($role === 'sekretaris') {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => '&#8862;', 'route' => 'sekretaris', 'anchor' =>
                'dashboard'],
                'data_kelas' => ['label' => 'Data Kelas', 'icon' => '&#127891;', 'route' => 'sekretaris', 'anchor' =>
                'data-kelas'],
                'jurnal_kelas' => ['label' => 'Jurnal Kelas', 'icon' => '&#128203;', 'route' => 'sekretaris', 'anchor'
                => 'jurnal-kelas'],
                'kehadiran' => ['label' => 'Kehadiran', 'icon' => '&#9989;', 'route' => 'sekretaris', 'anchor' =>
                'kehadiran'],
                'rekap' => ['label' => 'Rekap', 'icon' => '&#128202;', 'route' => 'sekretaris', 'anchor' => 'rekap'],
                ];
                } else {
                $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => '&#8862;', 'route' => 'dashboard'],
                'riwayat' => ['label' => 'Laporan Tervalidasi', 'icon' => '&#128202;', 'route' => 'riwayat'],
                'data_master' => ['label' => 'Data Master', 'icon' => '&#9881;', 'route' => 'data-master'],
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

                    @if ($item['icon'] !== '')
                    <span style="font-size:15px; width:20px; text-align:center;">
                        {!! $item['icon'] !!}
                    </span>
                    @endif

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