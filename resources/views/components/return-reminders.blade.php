@php
    $dueReturns = auth()->user()?->isBorrower()
        ? auth()->user()->reservations()->checkedOut()->where('ends_at', '<=', now()->addHours(2))->with('equipment')->orderBy('ends_at')->limit(3)->get()
        : collect();
@endphp
@if ($dueReturns->isNotEmpty())
    <section aria-label="Return reminders" class="mb-4 space-y-2">
        @foreach ($dueReturns as $dueReturn)
            <p role="status" class="rounded-md border px-4 py-3 text-sm {{ $dueReturn->isOverdue() ? 'border-red-300 bg-red-50 text-red-900' : 'border-amber-300 bg-amber-50 text-amber-900' }}">
                <a class="font-semibold underline" href="{{ route('reservations.show', $dueReturn) }}">{{ $dueReturn->equipment->name }}</a>:
                @if ($dueReturn->isOverdue())
                    Overdue since {{ $dueReturn->ends_at->format('M j, Y g:i A') }}. Please arrange its return with your lab manager.
                @else
                    Due back {{ $dueReturn->ends_at->diffForHumans() }} ({{ $dueReturn->ends_at->format('g:i A') }}).
                @endif
            </p>
        @endforeach
    </section>
@endif
