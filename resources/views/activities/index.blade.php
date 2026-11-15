<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Activity history</h1></x-slot>
    <p class="mb-4 text-sm text-slate-600">History is limited to your reservations, lab or department.</p>
    <x-list-summary :paginator="$activities" />
    <ol class="space-y-3">
        @forelse ($activities as $activity)
            <li>
                <x-card>
                    <p class="font-semibold">{{ ucfirst(str_replace(['.', '_'], ' ', $activity->event)) }}</p>
                    <p class="text-sm">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->created_at->format('M j, Y g:i A') }}</p>
                    @if ($activity->reservation_id)
                        <a class="text-sm text-blue-700 underline" href="{{ route('reservations.show', $activity->reservation_id) }}">Reservation #{{ $activity->reservation_id }}</a>
                    @elseif ($activity->equipment)
                        <a class="text-sm text-blue-700 underline" href="{{ route('equipment.show', $activity->equipment) }}">{{ $activity->equipment->name }}</a>
                    @endif
                    @if ($activity->details)
                        <dl class="mt-2 text-sm">
                            @foreach ($activity->details as $key => $value)
                                <div><dt class="inline font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}:</dt> <dd class="inline break-words">{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </x-card>
            </li>
        @empty
            <li><x-empty-state>No recorded activity in your scope yet.</x-empty-state></li>
        @endforelse
    </ol>
    <div class="mt-4">{{ $activities->links() }}</div>
</x-app-layout>
