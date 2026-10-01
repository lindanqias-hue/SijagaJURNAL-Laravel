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

{{-- NAVIGASI SIDEBAR — sumber tunggal: NavMenu --}}
        <nav class="sidebar-nav" x-data="navAccordion()" x-init="restore()">
            @php
            $role = session('role', 'guru');
            $isGuruPiket = ($role === 'guru' && session('id_pengguna'))
                ? app(\App\Services\GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'))
                : false;
            $groups = app(\App\Support\NavMenu::class)->untukRole($role, $isGuruPiket);
            @endphp

            @foreach ($groups as $groupIndex => $group)
                @php
                // Item langsung (Dashboard) tanpa kelompok.
                $isDirect = empty($group['items']) && ! empty($group['route']);
                $groupKey = $group['anchor'] ?? $group['label'];
                $groupItems = $group['items'] ?? [];
                // Kelompok terbuka kalau ada item aktif di dalamnya.
                $hasActive = false;
                foreach ($groupItems as $gi) {
                    $u = app(\App\Support\NavMenu::class)->url($gi);
                    if ($u !== null) {
                        $isActive = request()->routeIs($gi['route'])
                            && in_array($gi['anchor'] ?? '', ['', 'dashboard'], true);
                        if ($role === 'sekretaris' && request()->routeIs('sekretaris')) {
                            $isActive = ($gi['anchor'] ?? '') === request()->query('menu', 'dashboard');
                        }
                        if ($isActive) { $hasActive = true; break; }
                    }
                }
                @endphp

                @if ($isDirect)
                    @php
                    $url = app(\App\Support\NavMenu::class)->url($group);
                    $isActive = request()->routeIs($group['route'])
                        && in_array($group['anchor'] ?? '', ['', 'dashboard'], true);
                    if ($role === 'sekretaris' && request()->routeIs('sekretaris')) {
                        $isActive = ($group['anchor'] ?? '') === request()->query('menu', 'dashboard');
                    }
                    @endphp
                    @if ($url !== null)
                    <a href="{{ $url }}"
                        class="nav-link {{ $isActive ? 'active' : '' }}"
                        data-nav-anchor="{{ $group['anchor'] ?? '' }}"
                        data-route-active="{{ $isActive ? 'true' : 'false' }}">
                        <span aria-hidden="true" style="font-size:15px; width:20px; text-align:center;"></span>
                        {{ $group['label'] }}
                        <span class="dot" @if (!$isActive) hidden @endif></span>
                    </a>
                    @endif
                @else
                <div class="nav-group" data-group="{{ $groupKey }}">
                    <button type="button"
                        class="nav-group-header"
                        :class="{ 'is-open': openGroups['{{ $groupKey }}'] ?? {{ $hasActive ? 'true' : 'false' }} }"
                        @click="toggleGroup('{{ $groupKey }}')"
                        aria-expanded="{{ $hasActive ? 'true' : 'false' }}"
                        aria-controls="group-{{ $groupIndex }}">
                        <span class="nav-group-icon">{{ $group['icon'] ?? '•' }}</span>
                        <span class="nav-group-label">{{ $group['label'] }}</span>
                        <span class="nav-group-chevron" :class="{ 'is-rotated': openGroups['{{ $groupKey }}'] ?? {{ $hasActive ? 'true' : 'false' }} }">‹</span>
                    </button>
                    <div class="nav-group-body" id="group-{{ $groupIndex }}"
                         x-show="openGroups['{{ $groupKey }}'] ?? {{ $hasActive ? 'true' : 'false' }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-9999"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 max-h-9999"
                         x-transition:leave-end="opacity-0 max-h-0">
                        @foreach ($groupItems as $item)
                            @php
                            $url = app(\App\Support\NavMenu::class)->url($item);
                            $isActive = false;
                            if ($url !== null) {
                                $isActive = request()->routeIs($item['route'])
                                    && in_array($item['anchor'] ?? '', ['', 'dashboard'], true);
                                if ($role === 'sekretaris' && request()->routeIs('sekretaris')) {
                                    $isActive = ($item['anchor'] ?? '') === request()->query('menu', 'dashboard');
                                }
                            }
                            @endphp
                            @if ($url !== null)
                            <a href="{{ $url }}"
                                class="nav-link nav-link-sub {{ $isActive ? 'active' : '' }}"
                                data-nav-anchor="{{ $item['anchor'] ?? '' }}"
                                data-route-active="{{ $isActive ? 'true' : 'false' }}">
                                <span aria-hidden="true" style="font-size:13px; width:16px; text-align:center;"></span>
                                {{ $item['label'] }}
                                <span class="dot" @if (!$isActive) hidden @endif></span>
                            </a>
                            @endif
                        @endforeach
                    </div>
                </div>
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
                <nav class="section-tabs d-lg-none" aria-label="Navigasi halaman" data-section-nav>
                    <div class="tab-pill-group">
                        @php
                        $flat = app(\App\Support\NavMenu::class)->flatten($groups);
                        @endphp
                        @foreach ($flat as $item)
                            @php
                            $url = app(\App\Support\NavMenu::class)->url($item);
                            $isActive = false;
                            if ($url !== null) {
                                $isActive = request()->routeIs($item['route'])
                                    && in_array($item['anchor'] ?? '', ['', 'dashboard'], true);
                                if ($role === 'sekretaris' && request()->routeIs('sekretaris')) {
                                    $isActive = ($item['anchor'] ?? '') === request()->query('menu', 'dashboard');
                                }
                            }
                            $navPath = $url !== null ? parse_url($url, PHP_URL_PATH) : '';
                            @endphp
                            @if ($url !== null)
                            <a href="{{ $url }}"
                                class="tab-pill {{ $isActive ? 'active' : '' }}"
                                data-nav-path="{{ $navPath }}"
                                data-nav-anchor="{{ $item['anchor'] ?? '' }}"
                                data-nav-menu="{{ $role === 'sekretaris' ? 'true' : 'false' }}"
                                data-route-active="{{ $isActive ? 'true' : 'false' }}"
                                @if ($isActive) aria-current="page" @endif>
                                {{ $item['label'] }}
                            </a>
                            @endif
                        @endforeach
                    </div>
                </nav>
                {{ $slot }}
            </main>
        </div>

    </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    @livewireScripts
    
    <script>
        // Accordion state navigator sidebar — simpan di localStorage per role.
        function navAccordion() {
            return {
                openGroups: {},
                storageKey() {
                    const role = document.querySelector('meta[name="role"]')?.content
                        || (window.SIJAGA_ROLE || 'guru');
                    return 'sijaga:nav:' + role;
                },
                restore() {
                    try {
                        const raw = localStorage.getItem(this.storageKey());
                        if (raw) {
                            this.openGroups = JSON.parse(raw);
                        }
                    } catch (e) { /* ignore */ }
                },
                toggleGroup(key) {
                    if (this.openGroups[key]) {
                        delete this.openGroups[key];
                    } else {
                        this.openGroups[key] = true;
                    }
                    this.persist();
                },
                persist() {
                    try {
                        localStorage.setItem(this.storageKey(), JSON.stringify(this.openGroups));
                    } catch (e) { /* ignore */ }
                },
            };
        }
    </script>
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
            const nav = document.querySelector('[data-section-nav]');

            if (!nav) {
                return;
            }

            const links = [...nav.querySelectorAll('.tab-pill')];
            const normalizePath = (path) => path.replace(/\/+$/, '') || '/';

            const syncActiveLink = () => {
                const currentPath = normalizePath(window.location.pathname);
                const currentHash = decodeURIComponent(window.location.hash.slice(1));
                const currentMenu = new URLSearchParams(window.location.search).get('menu');
                const linksForPath = links.filter((link) => normalizePath(link.dataset.navPath) === currentPath);
                const hashLink = currentHash
                    ? linksForPath.find((link) => link.dataset.navAnchor === currentHash)
                    : null;
                const menuLink = currentMenu
                    ? linksForPath.find((link) => link.dataset.navMenu === 'true' && link.dataset.navAnchor === currentMenu)
                    : null;
                const activeLink = menuLink ?? hashLink ?? linksForPath.find((link) => link.dataset.routeActive === 'true');

                links.forEach((link) => {
                    const isActive = link === activeLink;

                    link.classList.toggle('active', isActive);

                    if (isActive) {
                        link.setAttribute('aria-current', 'page');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };

            syncActiveLink();
            window.addEventListener('hashchange', syncActiveLink);
            window.addEventListener('pageshow', syncActiveLink);
            document.addEventListener('livewire:navigated', syncActiveLink);
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
