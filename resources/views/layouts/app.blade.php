<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->user()->preferences['theme'] ?? 'system' }}" @class(['dark' => (auth()->user()->preferences['theme'] ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Equip Me Maybe') }}</title>
    <script>
        if (document.documentElement.dataset.theme === 'dark' ||
            (document.documentElement.dataset.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:p-3 print:hidden">
        Skip to content
    </a>

    <div x-data="{ sidebar: false }"
         @keydown.escape.window="sidebar = false"
         class="lg:grid lg:min-h-screen lg:grid-cols-[15rem_minmax(0,1fr)] print:block">

        @include('layouts.navigation')

        <div class="min-w-0">
            {{-- Mobile top bar --}}
            <div class="lg:hidden flex items-center gap-3 bg-slate-900 text-white px-4 h-14 print:hidden">
                <button type="button"
                        @click="sidebar = true"
                        aria-label="Open menu"
                        aria-controls="workspace-navigation"
                        :aria-expanded="sidebar.toString()"
                        class="p-2 rounded-md hover:bg-slate-800">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor"
                         stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <span class="font-semibold">Equip Me Maybe</span>
            </div>

            <x-workspace-tools />

            @isset($header)
                <header class="border-b border-slate-200 bg-white print:hidden">
                    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="main-content" tabindex="-1"
                  class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 print:px-0 print:py-0">
                <x-flash />
                @if (view()->exists('components.return-reminders'))
                    <x-return-reminders />
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-confirm-action />
</body>
</html>
