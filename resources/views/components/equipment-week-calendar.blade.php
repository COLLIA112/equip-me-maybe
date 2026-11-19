@props(['equipment', 'week', 'bookings'])
<section class="mt-6" aria-labelledby="week-calendar-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 id="week-calendar-title" class="text-lg font-bold">Weekly booking calendar</h2>
        <div class="flex flex-wrap gap-x-4 text-sm font-semibold text-blue-700">
            <a href="{{ route('equipment.show', [$equipment, 'week' => $week->copy()->subWeek()->toDateString()]) }}" class="inline-flex min-h-11 items-center underline underline-offset-4" aria-label="Previous week starting {{ $week->copy()->subWeek()->format('F j, Y') }}">Previous week</a>
            <a href="{{ route('equipment.show', [$equipment, 'week' => now()->startOfWeek()->toDateString()]) }}" class="inline-flex min-h-11 items-center underline underline-offset-4">This week</a>
            <a href="{{ route('equipment.show', [$equipment, 'week' => $week->copy()->addWeek()->toDateString()]) }}" class="inline-flex min-h-11 items-center underline underline-offset-4" aria-label="Next week starting {{ $week->copy()->addWeek()->format('F j, Y') }}">Next week</a>
        </div>
    </div>
    <div class="responsive-table rounded-lg border border-slate-200 bg-white" role="region" aria-label="Weekly equipment bookings" tabindex="0">
        <table class="w-full table-fixed text-sm tabular-nums" style="min-width: 56rem">
            <caption class="p-3 text-left font-semibold">Week of {{ $week->format('F j, Y') }}. Times shown in {{ config('app.timezone') }}.</caption>
            <thead><tr>
                @for ($day = 0; $day < 7; $day++)
                    <th scope="col" class="border-b border-r border-slate-200 bg-slate-50 p-3 text-left">{{ $week->copy()->addDays($day)->format('D, M j') }}</th>
                @endfor
            </tr></thead>
            <tbody><tr>
                @for ($day = 0; $day < 7; $day++)
                    @php
                        $date = $week->copy()->addDays($day);
                        $daily = $bookings->filter(fn ($booking) => $booking->starts_at < $date->copy()->addDay() && $booking->ends_at > $date);
                    @endphp
                    <td class="border-r border-slate-200 p-2 align-top">
                        @forelse ($daily as $booking)
                            <div class="mb-2 rounded-md bg-blue-50 p-2 text-blue-900">
                                <p class="font-semibold">{{ $booking->status->label() }}</p>
                                <p><time datetime="{{ $booking->starts_at->toIso8601String() }}">{{ $booking->starts_at->format('M j, Y g:i A') }}</time> to <time datetime="{{ $booking->ends_at->toIso8601String() }}">{{ $booking->ends_at->format('M j, Y g:i A') }}</time></p>
                            </div>
                        @empty
                            <p class="p-2 text-slate-600">No bookings</p>
                        @endforelse
                    </td>
                @endfor
            </tr></tbody>
        </table>
    </div>
</section>
