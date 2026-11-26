
<x-app-layout>

    <x-slot name="header">

        <div class="flex w-full flex-wrap items-center justify-between gap-4">

            <div>

                <p class="mb-1 text-sm font-semibold text-blue-700">
                    Laboratory Management
                </p>

                <h1 class="page-title">
                    Reservation queue
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    Review requests and manage equipment handovers and returns.
                </p>

            </div>

            <a href="{{ route('reservations.counter') }}"
               class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                <svg class="h-5 w-5"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <path d="M3 7h18 M3 17h18 M7 3l-4 4 4 4 M17 13l4 4-4 4"/>

                </svg>

                Open handover and return counter

            </a>

        </div>

    </x-slot>

    {{-- Reservation filters --}}

    <form method="GET" class="ui-card mb-5">

        <h2 class="text-lg font-bold text-slate-950">
            Filter reservation requests
        </h2>

        <p class="mb-5 mt-2 text-sm text-slate-600">
            Search borrowers and filter reservation requests.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

            <div>

                <label for="queue-status"
                       class="mb-2 block text-sm font-semibold">

                    Status

                </label>

                <select id="queue-status"
                        name="status"
                        class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                    <option value="">Open reservations</option>

                    @foreach (\App\Enums\ReservationStatus::cases() as $status)

                        <option value="{{ $status->value }}"
                                @selected(($filters['status'] ?? '') === $status->value)>

                            {{ $status->label() }}

                        </option>

                    @endforeach

                </select>

            </div>

            <div>

                <label for="queue-borrower"
                       class="mb-2 block text-sm font-semibold">

                    Borrower name or email

                </label>

                <input id="queue-borrower"
                       name="q"
                       type="search"
                       value="{{ $filters['q'] ?? '' }}"
                       maxlength="150"
                       placeholder="Search borrower"
                       class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

            </div>

            <div>

                <label for="queue-overdue"
                       class="mb-2 block text-sm font-semibold">

                    Return date

                </label>

                <select id="queue-overdue"
                        name="overdue"
                        class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                    <option value="">All return dates</option>

                    <option value="1"
                            @selected(($filters['overdue'] ?? '') === '1')>

                        Overdue returns

                    </option>

                </select>

            </div>

        </div>

        @foreach (['sort', 'direction'] as $key)

            @if (isset($filters[$key]))

                <input type="hidden"
                       name="{{ $key }}"
                       value="{{ $filters[$key] }}">

            @endif

        @endforeach

        <div class="mt-5 flex flex-wrap gap-3">

            <button type="submit"
                    class="min-h-11 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                Apply filters

            </button>

            @if ($activeFilters)

                <a href="{{ route('reservations.queue', ['clear' => 1]) }}"
                   class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold text-blue-700 underline">

                    Clear filters

                </a>

            @endif

        </div>

    </form>

    {{-- Bulk reservation management --}}

    <form id="bulk-reservations"
          method="POST"
          action="{{ route('reservations.bulk') }}"
          data-confirm="Apply this action to the selected pending reservations?"
          class="ui-card mb-5">

        @csrf

        <h2 class="text-lg font-bold text-slate-950">
            Bulk actions
        </h2>

        <p id="bulk-action-help"
           class="mb-5 mt-2 text-sm text-slate-600">

            Select pending reservations from the table below to approve
            or reject multiple requests at once. Maximum of 50 requests.

        </p>

        <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-3">

            <div>

                <label for="bulk-action"
                       class="mb-2 block text-sm font-semibold">

                    Selected pending requests

                </label>

                <select name="action"
                        id="bulk-action"
                        aria-describedby="bulk-action-help"
                        class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                    <option value="approve">
                        Approve selected
                    </option>

                    <option value="reject">
                        Reject selected
                    </option>

                </select>

            </div>

            <div>

                <label for="bulk-reason"
                       class="mb-2 block text-sm font-semibold">

                    Rejection reason (optional)

                </label>

                <input name="rejection_reason"
                       id="bulk-reason"
                       maxlength="1000"
                       placeholder="Enter a reason"
                       class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

            </div>

            <button type="submit"
                    class="min-h-11 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                Apply to selected (maximum 50)

            </button>

        </div>

    </form>

    {{-- Bulk action errors --}}

    @if (session('bulk_result.errors'))

        <div role="status"
             class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">

            <p class="font-bold">
                Some selected requests could not be changed:
            </p>

            <ul class="mt-2 list-disc space-y-1 pl-5">

                @foreach (session('bulk_result.errors') as $id => $messages)

                    <li>

                        Reservation #{{ $id }}:
                        {{ implode(' ', $messages) }}

                    </li>

                @endforeach

            </ul>

        </div>

    @endif

    {{-- Results summary --}}

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">

        <h2 class="text-lg font-bold text-slate-950">
            Reservation requests
        </h2>

        <p class="text-sm text-slate-600">

            Showing {{ $reservations->firstItem() ?? 0 }}
            –{{ $reservations->lastItem() ?? 0 }}
            of {{ $reservations->total() }}

        </p>

    </div>

    <p class="mb-3 text-sm text-slate-600">
        Select an equipment name to view reservation details.
        Scroll horizontally to access all table columns.
    </p>

    {{-- Reservation management table --}}

    <x-card role="region"
            aria-label="Reservation queue table"
            tabindex="0"
            class="responsive-table ui-table p-0">

        <table class="min-w-full text-left text-sm">

            <caption class="sr-only">
                Laboratory reservation requests and management actions
            </caption>

            <thead class="bg-slate-100 text-slate-700">

                <tr>

                    <th scope="col" class="px-3 py-4">
                        <span class="sr-only">
                            Select pending request
                        </span>
                    </th>

                    @foreach ([
                        'equipment' => 'Equipment',
                        'borrower' => 'Borrower',
                        'starts_at' => 'Window',
                        'status' => 'Status'
                    ] as $key => $label)

                        <th scope="col"
                            class="px-4 py-4"
                            @if (($filters['sort'] ?? 'starts_at') === $key)
                                aria-sort="{{ ($filters['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}"
                            @endif>

                            <a class="underline underline-offset-4"
                               href="{{ request()->fullUrlWithQuery([
                                   'sort' => $key,
                                   'direction' => ($filters['sort'] ?? 'starts_at') === $key
                                       && ($filters['direction'] ?? 'asc') === 'asc'
                                       ? 'desc'
                                       : 'asc',
                                   'page' => null
                               ]) }}">

                                {{ $label }}

                            </a>

                        </th>

                    @endforeach

                    <th scope="col" class="px-4 py-4">
                        Documents
                    </th>

                    <th scope="col" class="px-4 py-4">
                        Actions
                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse ($reservations as $r)

                    @php

                        $ok = $compliance[$r->id] ?? false;

                        $missing = $missingDocuments[$r->id] ?? [];

                    @endphp

                    <tr class="border-t border-slate-200 align-top">

                        {{-- Bulk selection --}}

                        <td class="px-3 py-4">

                            @if ($r->status === \App\Enums\ReservationStatus::Pending)

                                <input type="checkbox"
                                       form="bulk-reservations"
                                       name="reservation_ids[]"
                                       value="{{ $r->id }}"
                                       aria-label="Select reservation {{ $r->id }} for {{ $r->equipment->name }}"
                                       class="h-5 w-5 rounded border-slate-400">

                            @endif

                        </td>

                        {{-- Equipment --}}

                        <th scope="row"
                            class="px-4 py-4 text-left font-medium">

                            <a href="{{ route('reservations.show', $r) }}"
                               class="font-semibold text-blue-700 underline underline-offset-4">

                                {{ $r->equipment->name }}

                            </a>

                            <p class="mt-1 text-xs text-slate-600">
                                Reservation #{{ $r->id }}
                            </p>

                            @if ($r->is_class_session)

                                <p class="mt-2 text-xs text-slate-600">
                                    Faculty class session
                                </p>

                            @endif

                        </th>

                        {{-- Borrower --}}

                        <td class="px-4 py-4">

                            {{ $r->user->name }}

                        </td>

                        {{-- Reservation schedule --}}

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

                        {{-- Reservation status --}}

                        <td class="px-4 py-4">

                            <x-badge :status="$r->status" />

                            @if ($r->isOverdue())

                                <span class="mt-2 inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">

                                    Overdue

                                </span>

                            @endif

                        </td>

                        {{-- Document compliance --}}

                        <td id="compliance-{{ $r->id }}"
                            class="px-4 py-4">

                            @if ($ok)

                                <div class="flex items-start gap-2 text-green-700">

                                    <svg class="h-4 w-4 shrink-0"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         stroke-linecap="round"
                                         stroke-linejoin="round"
                                         aria-hidden="true">

                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="m8 12 3 3 5-6"/>

                                    </svg>

                                    <span class="text-sm font-semibold">
                                        All acknowledged
                                    </span>

                                </div>

                            @else

                                <div class="text-sm text-red-700">

                                    <p class="font-semibold">
                                        Missing acknowledgement
                                    </p>

                                    <p class="mt-2 break-words">

                                        Must acknowledge:
                                        {{ implode(', ', $missing) }}.

                                    </p>

                                </div>

                            @endif

                        </td>

                        {{-- Reservation actions --}}

                        <td class="px-4 py-4">

                            <div class="flex min-w-48 flex-col items-start gap-4">

                                {{-- Pending request --}}

                                @if ($r->status === \App\Enums\ReservationStatus::Pending)

                                    <form method="POST"
                                          action="{{ route('reservations.approve', $r) }}"
                                          class="w-full">

                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                @disabled(! $ok)
                                                aria-describedby="compliance-{{ $r->id }}"
                                                class="min-h-11 w-full rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-40">

                                            Approve

                                        </button>

                                    </form>

                                    <form method="POST"
                                          action="{{ route('reservations.reject', $r) }}"
                                          data-confirm="Reject this request?"
                                          class="w-full space-y-2">

                                        @csrf
                                        @method('PATCH')

                                        <label for="rejection-reason-{{ $r->id }}"
                                               class="block text-xs font-semibold">

                                            Rejection reason (optional)

                                        </label>

                                        <input id="rejection-reason-{{ $r->id }}"
                                               name="rejection_reason"
                                               maxlength="1000"
                                               value="{{ old('rejection_reason') }}"
                                               class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                                        <button type="submit"
                                                class="min-h-11 w-full rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">

                                            Reject

                                        </button>

                                    </form>

                                {{-- Approved request --}}

                                @elseif ($r->status === \App\Enums\ReservationStatus::Approved)

                                    <form method="POST"
                                          action="{{ route('reservations.handover', $r) }}"
                                          class="w-full">

                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                @disabled(! $ok)
                                                aria-describedby="compliance-{{ $r->id }}"
                                                class="min-h-11 w-full rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40">

                                            Hand over

                                        </button>

                                    </form>

                                    <form method="POST"
                                          action="{{ route('reservations.revoke', $r) }}"
                                          data-confirm="Cancel this approved booking?"
                                          class="w-full space-y-2">

                                        @csrf
                                        @method('PATCH')

                                        <label for="cancellation-reason-{{ $r->id }}"
                                               class="block text-xs font-semibold">

                                            Cancellation reason (required)

                                        </label>

                                        <input id="cancellation-reason-{{ $r->id }}"
                                               name="cancellation_reason"
                                               required
                                               minlength="5"
                                               maxlength="1000"
                                               class="min-h-11 w-full rounded-lg border-slate-300 text-sm">

                                        <button type="submit"
                                                class="min-h-11 w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">

                                            Cancel booking

                                        </button>

                                    </form>

                                {{-- Checked-out equipment --}}

                                @elseif ($r->status === \App\Enums\ReservationStatus::CheckedOut)

                                    <form method="POST"
                                          action="{{ route('reservations.return', $r) }}"
                                          data-confirm="Record this item as returned?"
                                          class="w-full space-y-3">

                                        @csrf
                                        @method('PATCH')

                                        @include('reservations._return-condition')

                                        <button type="submit"
                                                class="min-h-11 w-full rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                                            Receive return

                                        </button>

                                    </form>

                                @else

                                    <a href="{{ route('reservations.show', $r) }}"
                                       class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline">

                                        View details

                                    </a>

                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="7" class="p-6">

                            <x-empty-state>

                                {{ $activeFilters
                                    ? 'No reservations match your filters.'
                                    : 'No open reservations for your lab.' }}

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
