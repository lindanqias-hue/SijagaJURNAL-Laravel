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

        <meta name="role" content="{{ session('role', 'guru') }}">

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
    @php
    $role = session('role', 'guru');
    $isGuruPiket = ($role === 'guru' && session('id_pengguna'))
        ? app(\App\Services\GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'))
        : false;
    $navMenu = app(\App\Support\NavMenu::class);
    $groups = $navMenu->untukRole($role, $isGuruPiket);
    $flat = $navMenu->flatten($groups);
    $isItemActive = static function (array $item) use ($role): bool {
        $isActive = request()->routeIs($item['route'])
            && in_array($item['anchor'] ?? '', ['', 'dashboard'], true);
        if ($role === 'sekretaris' && request()->routeIs('sekretaris')) {
            $isActive = ($item['anchor'] ?? '') === request()->query('menu', 'dashboard');
        }

        return $isActive;
    };

    $topbarTitle = 'SIJAGA';
    foreach ($flat as $menuItem) {
        if ($navMenu->url($menuItem) !== null && $isItemActive($menuItem)) {
            $topbarTitle = $menuItem['label'];

            break;
        }
    }
    @endphp
    <div class="app-wrapper">

        {{-- TOPBAR (semua breakpoint) --}}
        <header class="app-topbar">
            <button class="btn-toggle d-lg-none" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#sidebarNav" aria-label="Buka navigasi">
                &#9776;
            </button>
            <div class="topbar-title" data-topbar-title>{{ $topbarTitle }}</div>
            <div class="layout-clock" aria-label="Jam dan tanggal saat ini">
                <time class="layout-clock-time" data-layout-clock-time></time>
                <time class="layout-clock-date" data-layout-clock-date></time>
            </div>
        </header>

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

            {{-- NAVIGASI SIDEBAR — sumber tunggal: NavMenu (flat, tanpa accordion) --}}
            <nav class="sidebar-nav" aria-label="Navigasi utama">
                @foreach ($groups as $group)
                    @php
                    $isDirect = empty($group['items']) && ! empty($group['route']);
                    $groupItems = $group['items'] ?? [];
                    @endphp

                    @if ($isDirect)
                        @php $url = $navMenu->url($group); @endphp
                        @if ($url !== null)
                        <a href="{{ $url }}"
                            class="nav-link {{ $isItemActive($group) ? 'active' : '' }}"
                            data-nav-anchor="{{ $group['anchor'] ?? '' }}"
                            data-route-active="{{ $isItemActive($group) ? 'true' : 'false' }}">
                            <span class="nav-icon">{!! \App\Support\NavIcon::svg($group['icon'] ?? 'dashboard') !!}</span>
                            {{ $group['label'] }}
                        </a>
                        @endif
                    @else
                        <div class="nav-section-label">{{ $group['label'] }}</div>
                        @foreach ($groupItems as $item)
                            @php $url = $navMenu->url($item); @endphp
                            @if ($url !== null)
                            <a href="{{ $url }}"
                                class="nav-link {{ $isItemActive($item) ? 'active' : '' }}"
                                data-nav-anchor="{{ $item['anchor'] ?? '' }}"
                                data-route-active="{{ $isItemActive($item) ? 'true' : 'false' }}">
                                <span class="nav-icon">{!! \App\Support\NavIcon::svg($item['icon'] ?? 'circle') !!}</span>
                                {{ $item['label'] }}
                            </a>
                            @endif
                        @endforeach
                    @endif
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
            const titleEl = document.querySelector('[data-topbar-title]');

            const syncTitle = () => {
                if (!titleEl) {
                    return;
                }

                const override = document.querySelector('[data-page-title]');
                const active = nav.querySelector('.nav-link.active');
                const label = override
                    ? override.textContent.trim()
                    : (active ? active.textContent.trim() : '');

                titleEl.textContent = label || 'SIJAGA';
            };

            const syncActiveLink = () => {
                const currentHash = decodeURIComponent(window.location.hash.slice(1));
                const hashLink = currentHash
                    ? links.find((link) => link.dataset.navAnchor === currentHash)
                    : null;
                const activeLink = hashLink ?? links.find((link) => link.dataset.routeActive === 'true');

                links.forEach((link) => {
                    link.classList.toggle('active', link === activeLink);
                });

                syncTitle();
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
                    });

                    syncTitle();
                });
            });
        })();

</script>

</body>
</html>
