<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Equip Me Maybe') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:p-3 print:hidden">Skip to content</a>
<div class="lg:grid lg:min-h-screen lg:grid-cols-[15rem_minmax(0,1fr)] print:block">
    @include('layouts.navigation')

    <div class="min-w-0">
        @isset($header)
            <header class="border-b border-slate-200 bg-white print:hidden">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">{{ $header }}</div>
            </header>
        @endisset

        <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 print:px-0 print:py-0">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
<x-confirm-action />
</body>
</html>
