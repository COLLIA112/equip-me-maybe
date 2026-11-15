<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Reserve {{ $equipment->name }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <form method="POST" action="{{ route('reservations.store', $equipment) }}" class="space-y-4" data-reservation-dates>
                @csrf
                <div>
                    <label for="starts_at" class="block text-sm font-medium">Start</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}" aria-describedby="reservation-date-help reservation-conflict" required class="mt-1 min-w-0 max-w-full w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium">End</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}" aria-describedby="reservation-date-help reservation-conflict" required class="mt-1 min-w-0 max-w-full w-full rounded-md border-slate-300 text-sm">
                </div>
                <p id="reservation-date-help" class="text-sm text-slate-600">End defaults to one hour after start. Reservation times are checked again when you submit.</p>
                <p id="reservation-conflict" role="status" aria-live="polite" class="hidden rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"></p>
                @php $bookingWindows = $booked->map(fn ($booking) => ['start' => $booking->starts_at->format('Y-m-d\TH:i'), 'end' => $booking->ends_at->format('Y-m-d\TH:i'), 'label' => $booking->starts_at->format('M j, Y g:i A').' to '.$booking->ends_at->format('M j, Y g:i A')])->values(); @endphp
                <script type="application/json" data-booked-windows>@json($bookingWindows)</script>
                @if (auth()->user()->hasRole(\App\Enums\Role::Faculty))
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input class="rounded border-slate-400" type="checkbox" name="is_class_session" value="1" @checked(old('is_class_session'))>
                        Faculty class session
                    </label>
                    <p class="text-sm text-slate-600">A class-session request displaces overlapping pending student requests. Approved and checked-out bookings still block the time slot.</p>
                @endif
                <p class="text-xs text-slate-600">Download and acknowledge the required documents before the lab manager can approve.</p>
                <div class="flex flex-wrap gap-2">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Request reservation</button>
                    <a href="{{ route('equipment.show', $equipment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </x-card>
        <x-card>
            <h2 class="mb-2 font-medium">Already booked</h2>
            @forelse ($booked as $b)
                <p class="border-t first:border-t-0 py-1.5 text-sm">{{ $b->starts_at->format('M j, Y g:i A') }} to {{ $b->ends_at->format('M j, Y g:i A') }}</p>
            @empty
                <x-empty-state>No upcoming bookings.</x-empty-state>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
