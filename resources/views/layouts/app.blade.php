<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Dyno Ads' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon.png') }}">

    {{-- Sebelum apa-apa dilukis, supaya tiada kilat putih masa buka mod gelap. --}}
    <script>
        (function () {
            var pilihan = null;
            try { pilihan = localStorage.getItem('dyno-tema'); } catch (e) {}
            var gelap = pilihan
                ? pilihan === 'gelap'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', gelap);
            document.documentElement.dataset.tema = gelap ? 'gelap' : 'terang';
        })();
    </script>
    <meta name="theme-color" content="#faf8ff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0a0715" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full antialiased">
    <div class="mx-auto flex min-h-full w-full max-w-md flex-col">
        <header class="flex items-center justify-between px-5 pb-2 pt-5">
            <a href="{{ auth()->check() ? route('ad-sets.create') : route('masuk') }}" wire:navigate class="flex items-center gap-2.5">
                <img src="{{ asset('img/logo.png') }}" alt="" class="h-10 w-10 shrink-0">
                <span class="text-lg font-extrabold tracking-tight">
                    <span class="gradient-text">DYNO</span><span>ADS</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('meta.connect') }}" wire:navigate class="icon-btn" aria-label="Sambungan akaun Meta"
                   title="{{ auth()->user()->name }}">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>

                <form method="POST" action="{{ route('keluar') }}">
                    @csrf
                    <button type="submit" class="icon-btn" aria-label="Keluar">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <path d="M16 17l5-5-5-5M21 12H9"/>
                        </svg>
                    </button>
                </form>
            @endauth

            <button type="button" onclick="tukarTema()" aria-label="Tukar mod terang atau gelap"
                    class="icon-btn">
                {{-- Matahari dipapar dalam mod gelap (tekan untuk terang), bulan sebaliknya. --}}
                <svg class="h-[18px] w-[18px] dark:hidden" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
                <svg class="hidden h-[18px] w-[18px] dark:block" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>
            </div>
        </header>

        <main class="flex-1 px-5 pb-24 pt-3">
            {{ $slot }}
        </main>

        <footer class="px-6 pb-8 pt-2 text-center text-[11px] leading-relaxed t-faint">
            App bantu optimize iklan. Ia tak jamin lead.<br>
            Tiada duit dibelanjakan sebelum anda tekan RUN.
        </footer>
    </div>

    <script>
        function tukarTema() {
            var gelap = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', gelap);
            document.documentElement.dataset.tema = gelap ? 'gelap' : 'terang';
            try { localStorage.setItem('dyno-tema', gelap ? 'gelap' : 'terang'); } catch (e) {}
        }
    </script>
    @livewireScripts
</body>
</html>
