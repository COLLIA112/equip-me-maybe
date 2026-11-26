
<x-app-layout>

    <x-slot name="header">
        <div>
            <p class="mb-1 text-sm font-semibold text-blue-700">
                Reservation Management
            </p>

            <h1 class="page-title">My reservations</h1>

            <p class="mt-2 text-sm text-slate-600">
                Track requests, booking schedules and returns.
            </p>
        </div>
    </x-slot>

    {{-- Reservation filters --}}

    <form method="GET" class="ui-card mb-5">

        <h2 class="mb-1 text-lg font-bold text-slate-950">
            Filter reservations
        </h2>

        <p class="mb-4 text-sm text-slate-600">
            Find reservations based on their current status.
        </p>

        <div class="flex flex-wrap items-end gap-4">

            <div class="min-w-0 flex-1 sm:flex-none">

                <label for="reservation-status"
                       class="mb-2 block text-sm font-semibold">
                    Status
                </label>

                <select id="reservation-status"
                        name="status"
                        class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                    <option value="">All statuses</option>

                    @foreach (\App\Enums\ReservationStatus::cases() as $status)

                        <option value="{{ $status->value }}"
                                @selected(($filters['status'] ?? '') === $status->value)>

                            {{ $status->label() }}

                        </option>

                    @endforeach

                </select>

            </div>

            @foreach (['sort', 'direction', 'overdue'] as $key)

                @if (isset($filters[$key]))

                    <input type="hidden"
                           name="{{ $key }}"
                           value="{{ $filters[$key] }}">

                @endif

            @endforeach

            <button type="submit"
                    class="min-h-11 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                Apply filters

            </button>

            @if ($activeFilters)

                <a href="{{ route('reservations.index', ['clear' => 1]) }}"
                   class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold text-blue-700 underline">

                    Clear filters

                </a>

            @endif

        </div>

    </form>

    {{-- Reservation results --}}

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">

        <h2 class="text-lg font-bold text-slate-950">
            Reservation history
        </h2>

        <p class="text-sm text-slate-600">

            Showing {{ $reservations->firstItem() ?? 0 }}
            –{{ $reservations->lastItem() ?? 0 }}
            of {{ $reservations->total() }}

        </p>

    </div>

    <p class="mb-3 text-sm text-slate-600">
        Select an equipment name to view reservation details.
        Scroll the table horizontally if needed.
    </p>

    <x-card role="region"
            aria-label="Reservations table"
            tabindex="0"
            class="responsive-table ui-table p-0">

        <table class="w-full text-left text-sm">

            <caption class="sr-only">
                My equipment reservations
            </caption>

            <thead class="bg-slate-100 text-slate-700">

                <tr>

                    @foreach ([
                        'equipment' => 'Equipment',
                        'starts_at' => 'Window',
                        'status' => 'Status'
                    ] as $key => $label)

                        <th scope="col"
                            class="px-4 py-4"
                            @if (($filters['sort'] ?? 'starts_at') === $key)
                                aria-sort="{{ ($filters['direction'] ?? 'desc') === 'asc' ? 'ascending' : 'descending' }}"
                            @endif>

                            <a class="underline underline-offset-4"
                               href="{{ request()->fullUrlWithQuery([
                                   'sort' => $key,
                                   'direction' => ($filters['sort'] ?? 'starts_at') === $key && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc',
                                   'page' => null
                               ]) }}">

                                {{ $label }}

                            </a>

                        </th>

                    @endforeach

                    <th scope="col" class="px-4 py-4">Lab</th>

                    <th scope="col" class="px-4 py-4">Actions</th>

                </tr>

            </thead>

            <tbody>

                @forelse ($reservations as $r)

                    <tr class="border-t border-slate-200">

                        <th scope="row"
                            class="px-4 py-4 text-left font-medium">

                            <a href="{{ route('reservations.show', $r) }}"
                               class="font-semibold text-blue-700 underline underline-offset-4">

                                {{ $r->equipment->name }}

                            </a>

                            <p class="mt-1 text-xs font-normal text-slate-600">
                                Reservation #{{ $r->id }}
                            </p>

                        </th>

                        <td class="px-4 py-4">

                            <p class="text-xs font-semibold text-slate-600">
                                Starts
                            </p>

                            <time datetime="{{ $r->starts_at->toIso8601String() }}">
                                {{ $r->starts_at->format('M j, Y g:i A') }}
                            </time>

                            <p class="mt-2 text-xs font-semibold text-slate-600">
                                Ends
                            </p>

                            <time datetime="{{ $r->ends_at->toIso8601String() }}">
                                {{ $r->ends_at->format('M j, Y g:i A') }}
                            </time>

                        </td>

                        <td class="px-4 py-4">

                            <x-badge :status="$r->status" />

                            @if ($r->isOverdue())

                                <span class="mt-2 inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                    Overdue
                                </span>

                            @endif

                            @if ($r->rejection_reason)

                                <p class="mt-2 max-w-sm text-sm text-slate-700">
                                    <strong>Reason:</strong>
                                    {{ $r->rejection_reason }}
                                </p>

                            @endif

                        </td>

                        <td class="px-4 py-4">
                            {{ $r->equipment->lab->name }}
                        </td>

                        <td class="px-4 py-4">

                            <div class="flex min-w-36 flex-col items-start gap-2">

                                <a href="{{ route('reservations.show', $r) }}"
                                   class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline">

                                    View details

                                </a>

                                @if ($r->canReserveAgain(auth()->user()))

                                    <a href="{{ route('reservations.again', $r) }}"
                                       class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                                        Reserve again

                                    </a>

                                @endif

                                @if ($r->status === \App\Enums\ReservationStatus::Approved)

                                    <a href="{{ route('reservations.calendar', $r) }}"
                                       class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline">

                                        Add to calendar (.ics)

                                    </a>

                                @endif

                                @can('cancel', $r)

                                    <form method="POST"
                                          action="{{ route('reservations.cancel', $r) }}"
                                          data-confirm="Cancel this reservation?">

                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                aria-label="Cancel reservation {{ $r->id }}"
                                                class="min-h-11 rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">

                                            Cancel reservation

                                        </button>

                                    </form>

                                @endcan

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="5" class="p-6">

                            <x-empty-state>

                                {{ $activeFilters
                                    ? 'No reservations match your filters.'
                                    : 'You have no reservations yet.' }}

                            </x-empty-state>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </x-card>

    <div class="mt-5">
        {{ $reservations->links() }}
    </div>

</x-app-layout>
