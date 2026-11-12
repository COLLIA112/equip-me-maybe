<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Welcome, {{ auth()->user()->name }}</h1></x-slot>

    @if ($role === \App\Enums\Role::SecurityAuditor)
        <x-card>
            <p class="font-medium">Campus Security Auditor</p>
            <p class="mt-1 text-sm text-slate-600">This role has no web dashboard. Use the checkpoint API with your token (POST /api/login).</p>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @if (isset($stats['equipment_total']))
                <x-card><p class="text-sm text-slate-500">Equipment in my lab</p><p class="text-3xl font-semibold">{{ $stats['equipment_total'] }}</p></x-card>
                <x-card><p class="text-sm text-slate-500">Under repair</p><p class="text-3xl font-semibold text-red-600">{{ $stats['under_repair'] }}</p></x-card>
                <x-card><p class="text-sm text-slate-500">Pending requests</p><p class="text-3xl font-semibold text-amber-600">{{ $stats['pending_requests'] }}</p>
                    <a href="{{ route('reservations.queue') }}" class="text-sm text-indigo-600 hover:underline">Open queue</a></x-card>
            @endif
            <x-card>
                <p class="font-medium">Quick links</p>
                <ul class="mt-2 space-y-1 text-sm text-indigo-600">
                    <li><a class="hover:underline" href="{{ route('equipment.index') }}">Browse equipment</a></li>
                    @if (auth()->user()->isBorrower())
                        <li><a class="hover:underline" href="{{ route('reservations.index') }}">My reservations</a></li>
                        <li><a class="hover:underline" href="{{ route('documents.checklist') }}">Required documents</a></li>
                    @endif
                </ul>
            </x-card>
        </div>

        @if (isset($stats['upcoming']))
            <x-card class="mt-6">
                <h2 class="font-medium mb-3">My upcoming reservations</h2>
                @forelse ($stats['upcoming'] as $r)
                    <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                        <div>
                            <a href="{{ route('equipment.show', $r->equipment) }}" class="font-medium hover:underline">{{ $r->equipment->name }}</a>
                            <p class="text-slate-500">{{ $r->starts_at->format('M j, g:i A') }} to {{ $r->ends_at->format('g:i A') }}</p>
                        </div>
                        <x-badge :status="$r->status" />
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nothing booked yet. Pick something from the catalog.</p>
                @endforelse
            </x-card>
        @endif
    @endif
</x-app-layout>
