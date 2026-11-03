<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#0e57a6" />

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png" />
    <link rel="manifest" href="/manifest.json" />

    @include('partials.seo')

    {{-- Terapkan tema sebelum React dimuat supaya tidak berkedip (kunci localStorage sama dengan ThemeProvider). --}}
    <script nonce="{{ Vite::cspNonce() }}">
        try {
            var t = localStorage.getItem('theme');
            var dark = t === 'dark' || ((t === null || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.add(dark ? 'dark' : 'light');
        } catch (e) {}
    </script>

    @viteReactRefresh
    @vite(['resources/js/main.tsx'])
    @inertiaHead
</head>
<body class="h-full">
    @inertia

    {{-- Pengunjung tanpa JavaScript (dan crawler sederhana) tetap mendapat identitas & kontak sekolah. --}}
    <noscript>
        <h1>{{ config('school.name') }}</h1>
        <p>{{ config('school.seo.description') }}</p>
        <p>{{ config('school.address') }}</p>
        <p>Telepon: {{ config('school.phone') }}. Email: {{ config('school.email') }}.</p>
        <p>Situs ini memerlukan JavaScript untuk menampilkan halaman lengkap.</p>
    </noscript>
</body>
</html>
