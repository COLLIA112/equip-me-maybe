<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Equip Me Maybe') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-800">
<div x-data="{ sidebar: false }" class="min-h-screen lg:flex">
    @include('layouts.navigation')

    <div class="flex-1 min-w-0">
        {{-- mobile top bar --}}
        <div class="lg:hidden flex items-center gap-3 bg-slate-900 text-white px-4 h-14">
            <button @click="sidebar = true" aria-label="Open menu" class="p-2 rounded-md hover:bg-slate-800">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-semibold">Equip Me Maybe</span>
        </div>

        @isset($header)
            <header class="bg-white border-b border-slate-200">
                <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
            </header>
        @endisset

        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>