<x-guest-layout>
    <h1 class="page-title">Reserve laboratory equipment</h1>
    <p class="mt-3 text-sm leading-6 text-slate-700">Browse the equipment catalog, complete safety requirements, and track your reservations.</p>
    <div class="mt-6 flex flex-wrap gap-3">
        @auth
            <a href="{{ route('dashboard') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Open dashboard</a>
        @else
            <a href="{{ route('login') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Sign in</a>
            <a href="{{ route('register') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Register as a student</a>
        @endauth
    </div>
</x-guest-layout>
