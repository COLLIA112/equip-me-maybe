
<x-app-layout>

    <x-slot name="header">

        <div class="flex w-full flex-wrap items-center justify-between gap-4">

            <div>

                <p class="mb-1 text-sm font-semibold text-blue-700">
                    Reservation Details
                </p>

                <h1 class="page-title">
                    Reservation #{{ $reservation->id }}
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    Review your reservation information and progress.
                </p>

            </div>

            <x-badge :status="$reservation->status" />

        </div>

    </x-slot>

    @php

        $status = $reservation->status;

        $ended = in_array($status, [
            \App\Enums\ReservationStatus::Rejected,
            \App\Enums\ReservationStatus::Cancelled
        ], true);

        $steps = [

            [
                'Requested',
                true,
                $reservation->created_at,
                $reservation->user?->name
            ],

            [
                'Approved',

                $reservation->approved_by !== null ||
                in_array($status, [
                    \App\Enums\ReservationStatus::Approved,
                    \App\Enums\ReservationStatus::CheckedOut,
                    \App\Enums\ReservationStatus::Returned
                ], true),

                $reservation->approved_at,
                $reservation->approver?->name
            ],

            [
                'Checked out',

                $reservation->handed_over_at !== null ||
                $status === \App\Enums\ReservationStatus::CheckedOut,

                $reservation->handed_over_at,
                $reservation->handoverManager?->name
            ],

            [
                'Returned',

                $reservation->returned_at !== null ||
                $status === \App\Enums\ReservationStatus::Returned,

                $reservation->returned_at,
                $reservation->returnManager?->name
            ],

        ];

    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-2">

        {{-- Reservation information --}}

        <x-card>

            <div class="mb-5 flex items-start gap-3">

                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"
                      aria-hidden="true">

                    <svg class="h-6 w-6"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.75"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                        <path d="M3 8v8l9 5 9-5V8"/>
                        <path d="M12 13v8"/>

                    </svg>

                </span>

                <div class="min-w-0">

                    <h2 class="break-words text-lg font-bold text-slate-950">
                        {{ $reservation->equipment->name }}
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">
                        Equipment reservation information
                    </p>

                </div>

            </div>

            <dl class="grid min-w-0 gap-4 sm:grid-cols-2">

                <div class="equipment-info-item">

                    <dt class="stat-label">Laboratory</dt>

                    <dd class="mt-2 break-words text-sm font-semibold">
                        {{ $reservation->equipment->lab->name }}
                    </dd>

                </div>

                <div class="equipment-info-item">

                    <dt class="stat-label">Borrower</dt>

                    <dd class="mt-2 break-words text-sm font-semibold">
                        {{ $reservation->user->name }}
                    </dd>

                </div>

                <div class="equipment-info-item">

                    <dt class="stat-label">Start date and time</dt>

                    <dd class="mt-2 text-sm font-semibold">

                        <time datetime="{{ $reservation->starts_at->toIso8601String() }}">
                            {{ $reservation->starts_at->format('M j, Y g:i A') }}
                        </time>

                    </dd>

                </div>

                <div class="equipment-info-item">

                    <dt class="stat-label">Return deadline</dt>

                    <dd class="mt-2 text-sm font-semibold">

                        <time datetime="{{ $reservation->ends_at->toIso8601String() }}">
                            {{ $reservation->ends_at->format('M j, Y g:i A') }}
                        </time>

                    </dd>

                </div>

                @if ($reservation->approver)

                    <div class="equipment-info-item">

                        <dt class="stat-label">Approved by</dt>

                        <dd class="mt-2 break-words text-sm font-semibold">
                            {{ $reservation->approver->name }}
                        </dd>

                    </div>

                @endif

                @if ($reservation->is_class_session)

                    <div class="equipment-info-item">

                        <dt class="stat-label">Purpose</dt>

                        <dd class="mt-2 text-sm font-semibold">
                            Faculty class session
                        </dd>

                    </div>

                @endif

            </dl>

            {{-- Rejection information --}}

            @if ($reservation->rejection_reason)

                <div role="note"
                     class="mt-5 rounded-xl bg-red-50 p-4 text-red-800">

                    <h3 class="text-sm font-bold">
                        Rejection reason
                    </h3>

                    <p class="mt-2 break-words text-sm">
                        {{ $reservation->rejection_reason }}
                    </p>

                </div>

            @endif

            {{-- Overdue notice --}}

            @if ($reservation->isOverdue())

                <div class="mt-5 flex items-start gap-3 rounded-xl bg-red-50 p-4 text-red-800">

                    <svg class="h-5 w-5 shrink-0"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">

                        <path d="M12 3 2 21h20L12 3z M12 9v4 M12 17h.01"/>

                    </svg>

                    <div>

                        <p class="text-sm font-bold">
                            Overdue reservation
                        </p>

                        <p class="mt-1 text-sm">
                            This reservation has passed its return deadline.
                        </p>

                    </div>

                </div>

            @endif

            {{-- Return condition --}}

            @if ($reservation->return_condition)

                <div class="equipment-info-item mt-5">

                    <h3 class="text-sm font-bold">
                        Return condition
                    </h3>

                    <p class="mt-2 text-sm">

                        {{ ucfirst(str_replace('_', ' ', $reservation->return_condition)) }}

                    </p>

                    @if ($reservation->return_notes)

                        <p class="mt-2 break-words text-sm text-slate-600">
                            {{ $reservation->return_notes }}
                        </p>

                    @endif

                </div>

            @endif

            {{-- Reservation actions --}}

            <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-200 pt-5">

                @if ($reservation->canReserveAgain(auth()->user()))

                    <a href="{{ route('reservations.again', $reservation) }}"
                       class="inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                        Reserve again

                    </a>

                @endif

                @if ($reservation->status === \App\Enums\ReservationStatus::Approved)

                    <a href="{{ route('reservations.calendar', $reservation) }}"
                       class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                        Add to calendar (.ics)

                    </a>

                @endif

                <a href="{{ route('equipment.show', $reservation->equipment) }}"
                   class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                    View equipment details

                </a>

            </div>

        </x-card>

        {{-- Reservation timeline --}}

        <x-card>

            <section aria-labelledby="reservation-timeline-heading">

                <h2 id="reservation-timeline-heading"
                    class="text-lg font-bold text-slate-950">

                    Reservation timeline

                </h2>

                <p class="mb-6 mt-2 text-sm text-slate-600">
                    Track each stage of the reservation process.
                </p>

                <ol class="reservation-timeline space-y-5 border-l-2 border-slate-200 pl-5">

                    @foreach ($steps as [$label, $reached, $time, $actor])

                        <li>

                            <div class="equipment-info-item">

                                <div class="flex flex-wrap items-center justify-between gap-2">

                                    <h3 class="text-sm font-bold">

                                        {{ $label }}

                                    </h3>

                                    @if ($reached)

                                        <x-badge status="completed" />

                                    @else

                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">

                                            {{ $ended ? 'Not reached' : 'Waiting' }}

                                        </span>

                                    @endif

                                </div>

                                <p class="mt-2 text-sm text-slate-600">

                                    @if ($time)

                                        <time datetime="{{ $time->toIso8601String() }}">
                                            {{ $time->format('M j, Y g:i A') }}
                                        </time>

                                    @else

                                        {{ $reached
                                            ? 'Completed; time not recorded'
                                            : ($ended ? 'Not reached' : 'Waiting for this stage') }}

                                    @endif

                                </p>

                                @if ($reached)

                                    <p class="mt-2 break-words text-xs text-slate-600">

                                        {{ $actor ? 'By '.$actor : 'Actor not recorded' }}

                                    </p>

                                @endif

                            </div>

                        </li>

                    @endforeach

                    @if ($ended)

                        <li>

                            <div class="rounded-xl bg-red-50 p-4 text-red-800">

                                <div class="flex flex-wrap items-center gap-2">

                                    <x-badge :status="$status" />

                                </div>

                                <p class="mt-2 text-sm font-semibold">

                                    {{ ($status === \App\Enums\ReservationStatus::Rejected
                                        ? $reservation->rejected_at
                                        : $reservation->cancelled_at)?->format('M j, Y g:i A') ?? 'Time not recorded' }}

                                </p>

                                <p class="mt-2 break-words text-sm">

                                    {{ ($status === \App\Enums\ReservationStatus::Rejected
                                        ? $reservation->rejector?->name
                                        : $reservation->canceller?->name)
                                        ?? ($status === \App\Enums\ReservationStatus::Rejected
                                            && $reservation->rejection_reason === \App\Services\ReservationService::FACULTY_PRIORITY_REASON
                                            ? 'Faculty priority rule'
                                            : 'Actor not recorded') }}

                                </p>

                            </div>

                        </li>

                    @endif

                </ol>

            </section>

        </x-card>

    </div>

</x-app-layout>
