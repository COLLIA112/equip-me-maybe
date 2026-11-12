<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Reserve {{ $equipment->name }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <form method="POST" action="{{ route('reservations.store', $equipment) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium">Start</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium">End</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <p class="text-xs text-slate-500">Download and acknowledge the required documents before the lab manager can approve.</p>
                <div class="flex gap-2">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Request reservation</button>
                    <a href="{{ route('equipment.show', $equipment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </x-card>
        <x-card>
            <h2 class="mb-2 font-medium">Already booked</h2>
            @forelse ($booked as $b)
                <p class="border-t first:border-t-0 py-1.5 text-sm">{{ $b->starts_at->format('M j, g:i A') }} to {{ $b->ends_at->format('g:i A') }}</p>
            @empty
                <p class="text-sm text-slate-500">No upcoming bookings.</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
