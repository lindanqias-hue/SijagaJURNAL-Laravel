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

    {{ $slot }}

    @livewireScripts

</body>
</html>
