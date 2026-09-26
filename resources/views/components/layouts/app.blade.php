```php
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'SIJAGA')
    </title>

    {{-- Bootstrap --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    {{-- CSS SIJAGA --}}
    <link
        href="{{ asset('assets/css/style.css') }}"
        rel="stylesheet"
    >

    {{-- CSS tambahan dari halaman --}}
    @stack('styles')

    {{-- Livewire --}}
    @livewireStyles
</head>

<body>

    {{-- Konten halaman --}}
    {{ $slot }}

    {{-- Bootstrap JS --}}
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

    {{-- Livewire --}}
    @livewireScripts

    {{-- JS tambahan dari halaman --}}
    @stack('scripts')

</body>
</html>
```
