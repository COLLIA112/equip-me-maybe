<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Handover and return counter</h1></x-slot>
    <x-card>
        <form method="POST" action="{{ route('reservations.counter.lookup') }}" class="space-y-3" data-counter-form>
            @csrf
            <label for="counter-code" class="block text-sm font-medium">Equipment code or reservation number</label>
            <input id="counter-code" name="code" value="{{ $code }}" required maxlength="100" placeholder="EQ-ABCD1234 or 123" class="w-full rounded-md border-slate-300" autocomplete="off" data-counter-code>
            <div class="flex flex-wrap gap-3"><button class="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Find booking</button>
                <button type="button" data-start-counter-scan class="rounded border border-slate-400 px-4 py-2">Scan QR with camera</button>
                <button type="button" data-stop-counter-scan hidden class="rounded border border-slate-400 px-4 py-2">Stop camera</button>
            </div>
            <p data-counter-scan-status role="status" aria-live="polite" class="text-sm text-slate-700">Enter a code, use a keyboard scanner, or scan with a supported camera browser.</p>
            <video data-counter-video hidden muted playsinline class="mt-3 max-h-72 w-full rounded-md"></video>
        </form>
    </x-card>
    @if (filled($code) && $reservations->isEmpty())<x-card class="mt-5"><x-empty-state>No approved or checked-out reservation was found in your lab. Check the code or booking status.</x-empty-state></x-card>@endif
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        @foreach ($reservations as $r)
            @php $missing = $missingDocuments[$r->id] ?? []; @endphp
            <x-card>
                <h2 class="text-lg font-bold"><a class="text-blue-700 underline" href="{{ route('reservations.show', $r) }}">Reservation #{{ $r->id }}</a></h2>
                <p class="mt-2 font-semibold">{{ $r->equipment->name }}</p><p>{{ $r->user->name }}</p>
                <p class="my-2 text-sm">{{ $r->starts_at->format('M j, Y g:i A') }} to {{ $r->ends_at->format('M j, Y g:i A') }}</p>
                <x-badge :status="$r->status" />
                @if ($r->isOverdue())<span class="rounded bg-red-100 px-2 py-1 text-sm font-semibold text-red-800">Overdue</span>@endif
                @if ($r->status === \App\Enums\ReservationStatus::Approved)
                    <p id="counter-compliance-{{ $r->id }}" class="my-3 text-sm">{{ $missing === [] ? 'All required documents acknowledged.' : 'Must acknowledge: '.implode(', ', $missing).'.' }}</p>
                    <form method="POST" action="{{ route('reservations.handover', $r) }}">@csrf @method('PATCH')
                        <button @disabled($missing !== []) aria-describedby="counter-compliance-{{ $r->id }}" class="rounded bg-blue-700 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50">Hand over</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('reservations.return', $r) }}" data-confirm="Record this item as returned?" class="mt-3 space-y-2">@csrf @method('PATCH')
                        @include('reservations._return-condition')
                        <button class="rounded bg-slate-700 px-4 py-2 text-white">Receive return</button>
                    </form>
                @endif
            </x-card>
        @endforeach
    </div>
</x-app-layout>
