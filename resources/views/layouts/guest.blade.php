<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Equip Me Maybe') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:p-3">Skip to content</a>
    <div class="grid min-h-screen lg:grid-cols-[minmax(18rem,2fr)_3fr]">
        <header class="flex flex-col justify-between gap-8 bg-[#10223f] p-6 text-white lg:p-12">
            <a href="/" class="w-fit text-lg font-bold tracking-tight">Equip Me Maybe</a>
            <p class="hidden max-w-sm text-3xl font-semibold leading-tight lg:block">University laboratory equipment reservations</p>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto flex min-w-0 w-full max-w-lg items-center p-4 py-8 sm:p-8">
            <div class="w-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">{{ $slot }}</div>
        </main>
    </div>
    <x-confirm-action />
</body>
</html>
