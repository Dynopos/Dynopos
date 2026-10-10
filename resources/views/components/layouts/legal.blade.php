@props(['title'])

<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} · Dyno Ads</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">

    {{-- Sebelum apa-apa dilukis, supaya tiada kilat putih masa buka mod gelap. --}}
    <script>
        (function () {
            var pilihan = null;
            try { pilihan = localStorage.getItem('dyno-tema'); } catch (e) {}
            var gelap = pilihan
                ? pilihan === 'gelap'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', gelap);
        })();
    </script>
    <meta name="theme-color" content="#faf8ff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0a0715" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased">
    <div class="mx-auto flex min-h-full w-full max-w-2xl flex-col">
        <header class="px-5 pb-2 pt-5">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 shrink-0">
                <span class="text-base font-extrabold tracking-tight">
                    <span class="gradient-text">DYNO</span><span>ADS</span>
                </span>
            </a>
        </header>

        <main class="flex-1 px-5 pb-20 pt-4 leading-relaxed">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ $title }}</h1>
            {{ $slot }}
        </main>

        <footer class="flex flex-wrap gap-x-4 gap-y-1 px-5 pb-10 text-xs t-faint">
            <a href="{{ route('privasi') }}" class="underline">Notis Privasi</a>
            <a href="{{ route('terma') }}" class="underline">Terma Perkhidmatan</a>
        </footer>
    </div>
</body>
</html>
