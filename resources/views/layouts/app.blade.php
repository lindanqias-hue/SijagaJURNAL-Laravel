<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SIJAGA</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="{{ asset('assets/css/style.css') }}"
        rel="stylesheet"
    >

    @livewireStyles
</head>

<body>

<div class="app-wrapper">

    {{-- TOPBAR MOBILE --}}
    <div class="topbar-mobile d-lg-none">

        <button
            class="btn-toggle"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebarNav"
        >
            &#9776;
        </button>

        <div class="sidebar-logo">&#127979;</div>

        <div class="brand-mini">
            SIJAGA
        </div>

    </div>


    {{-- SIDEBAR --}}
    <aside
        class="sidebar offcanvas-lg offcanvas-start"
        tabindex="-1"
        id="sidebarNav"
    >

        {{-- BRAND --}}
        <div class="sidebar-brand d-flex align-items-center justify-content-between">

            <div class="d-flex align-items-center gap-2">

                <div class="sidebar-logo">
                    &#127979;
                </div>

                <div>

                    <div
                        class="text-white fw-bold"
                        style="font-size:12px; line-height:1.2;"
                    >
                        SIJAGA
                    </div>

                    <div
                        style="color:rgba(255,255,255,.5); font-size:10px; line-height:1.3;"
                    >
                        Sistem Jurnal &amp; Absensi
                    </div>

                </div>

            </div>


            <button
                type="button"
                class="btn-close btn-close-white d-lg-none"
                data-bs-dismiss="offcanvas"
                aria-label="Close"
            ></button>

        </div>


        {{-- NAVIGASI --}}
<nav class="sidebar-nav">

    @php
        $role = session('role', 'guru');

        if ($role === 'guru') {

            $navItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => '&#8862;',
                    'route' => 'dashboard'
                ],
                'input_jurnal' => [
                    'label' => 'Input Jurnal',
                    'icon' => '&#9998;',
                    'route' => 'input-jurnal'
                ],
                'riwayat' => [
                    'label' => 'Riwayat Saya',
                    'icon' => '&#128203;',
                    'route' => 'riwayat'
                ],
            ];

        } elseif ($role === 'sekretaris') {

            $navItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => '&#8862;',
                    'route' => 'dashboard'
                ],
                'validasi' => [
                    'label' => 'Validasi Jurnal',
                    'icon' => '&#9989;',
                    'route' => 'validasi'
                ],
                'riwayat' => [
                    'label' => 'Riwayat Kelas',
                    'icon' => '&#128203;',
                    'route' => 'riwayat'
                ],
            ];

        } elseif ($role === 'guru_piket') {

            $navItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => '&#8862;',
                    'route' => 'guru-piket'
                ],
                'dispensasi' => [
                    'label' => 'Dispensasi Siswa',
                    'icon' => '&#128221;',
                    'route' => 'dispensasi'
                ],
                'rekap-dispensasi' => [
                    'label' => 'Rekap Dispensasi',
                    'icon' => '&#128202;',
                    'route' => 'rekap-dispensasi'
                ],
            ];

        } else {

            $navItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => '&#8862;',
                    'route' => 'dashboard'
                ],
                'riwayat' => [
                    'label' => 'Laporan Tervalidasi',
                    'icon' => '&#128202;',
                    'route' => 'riwayat'
                ],
                'data_master' => [
                    'label' => 'Data Master',
                    'icon' => '&#9881;',
                    'route' => 'data-master'
                ],
            ];
        }
    @endphp

    @foreach ($navItems as $key => $item)

        @php
            $isActive = request()->routeIs($item['route']);
        @endphp

        <a
            href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
            class="nav-link {{ $isActive ? 'active' : '' }}"
        >

            <span
                style="font-size:15px; width:20px; text-align:center;"
            >
                {!! $item['icon'] !!}
            </span>

            {{ $item['label'] }}

            @if ($isActive)
                <span class="dot"></span>
            @endif

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

                    <div
                        class="text-white fw-semibold text-truncate"
                        style="font-size:12px;"
                    >
                        {{ explode(',', $nama)[0] }}
                    </div>


                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">

                        @if(session('status_kepegawaian'))

                            <span
                                class="badge-status"
                                style="font-size:9px; padding:1px 5px;"
                            >
                                {{ session('status_kepegawaian') }}
                            </span>

                        @endif


                        @if(session('role') && session('role') !== 'guru')

                            <span
                                class="badge-status"
                                style="font-size:9px; padding:1px 5px;"
                            >
                                {{ strtoupper(session('role')) }}
                            </span>

                        @endif

                    </div>

                </div>

            </div>


            {{-- LOGOUT --}}
            <a
                href="{{ route('logout') }}"
                class="btn-logout"
            >
                <span>&#8592;</span>
                Keluar
            </a>

        </div>

    </aside>


    {{-- CONTENT --}}
    <div
        class="content-col"
        style="flex:1; min-width:0;"
    >

        <main class="main-content">

            {{ $slot }}

        </main>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@livewireScripts

</body>
</html>