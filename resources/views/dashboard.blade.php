
<x-app-layout>

    @php
        $isBorrower = auth()->user()->isBorrower();
        $isManager = $role === \App\Enums\Role::LabManager;

        /*
        |--------------------------------------------------------------------------
        | Dashboard statistic icons
        |--------------------------------------------------------------------------
        */

        $statIcons = [
            'pending requests' => 'M12 8v4l3 2 M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',

            'upcoming reservations' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2z',

            'active checkouts' => 'M12 3 3 8l9 5 9-5-9-5z M3 8v8l9 5 9-5V8 M12 13v8',

            'available equipment' => 'M12 3 3 8l9 5 9-5-9-5z M3 8v8l9 5 9-5V8 M12 13v8',

            'pending documents' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8',
        ];

        /*
        |--------------------------------------------------------------------------
        | Quick actions
        |--------------------------------------------------------------------------
        */

        $quickActions = [
            [
                'label' => 'Browse equipment',
                'description' => 'Explore the equipment catalog',
                'url' => route('equipment.index'),
                'icon' => 'M12 3 3 8l9 5 9-5-9-5z M3 8v8l9 5 9-5V8 M12 13v8',
            ],
        ];

        if ($isBorrower) {
            $quickActions[] = [
                'label' => 'My reservations',
                'description' => 'View and track your requests',
                'url' => route('reservations.index'),
                'icon' => 'M8 2v4 M16 2v4 M3 10h18 M3 4h18v17H3z',
            ];

            $quickActions[] = [
                'label' => 'Required documents',
                'description' => 'Review safety requirements',
                'url' => route('documents.checklist'),
                'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8',
            ];
        } else {
            $quickActions[] = [
                'label' => 'Requests needing approval',
                'description' => 'Review pending requests',
                'url' => route('reservations.queue', ['status' => 'pending']),
                'icon' => 'M12 8v4l3 2 M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
            ];

            $quickActions[] = [
                'label' => 'Overdue returns',
                'description' => 'Monitor overdue equipment',
                'url' => route('reservations.queue', ['overdue' => 1]),
                'icon' => 'M12 3 2 21h20L12 3z M12 9v4 M12 17h.01',
            ];

            $quickActions[] = [
                'label' => 'Reservation queue',
                'description' => 'Manage reservation requests',
                'url' => route('reservations.queue'),
                'icon' => 'M8 6h13 M8 12h13 M8 18h13 M3 6h1 M3 12h1 M3 18h1',
            ];

            $quickActions[] = [
                'label' => 'Add equipment',
                'description' => 'Register new equipment',
                'url' => route('equipment.create'),
                'icon' => 'M12 5v14 M5 12h14',
            ];

            $quickActions[] = [
                'label' => 'Report a breakdown',
                'description' => 'Manage equipment maintenance',
                'url' => route('maintenance.index'),
                'icon' => 'M14 6l4 4 M5 19l9-9 M16 3a5 5 0 0 0-6 6L3 16v5h5l7-7a5 5 0 0 0 6-6l-4 3-4-4 3-4z',
            ];
        }
    @endphp

    {{-- Page header --}}

    <x-slot name="header">

        <div class="min-w-0">

            <p class="mb-1 text-sm font-semibold text-blue-700">
                {{ $role->label() }}
            </p>

            <h1 class="page-title break-words">
                Welcome, {{ auth()->user()->name }}
            </h1>

            <p class="mt-2 text-sm text-slate-600">
                @if ($isBorrower)
                    Here's an overview of your equipment reservations
                    and borrowing activity.
                @else
                    Monitor equipment, reservations and laboratory
                    activity from your dashboard.
                @endif
            </p>

        </div>

    </x-slot>

    <div class="space-y-6">

        {{-- Welcome banner --}}

        <section
            aria-labelledby="dashboard-welcome"
            class="relative overflow-hidden rounded-xl bg-gradient-to-r from-blue-800 to-blue-700 p-6 text-white shadow-sm sm:p-8">

            {{-- Decorative background --}}

            <div
                class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/5"
                aria-hidden="true">
            </div>

            <div
                class="pointer-events-none absolute -bottom-24 right-16 h-56 w-56 rounded-full bg-white/5"
                aria-hidden="true">
            </div>

            <div class="relative max-w-2xl">

                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-blue-100">
                    Equip Me Maybe
                </p>

                <h2
                    id="dashboard-welcome"
                    class="text-xl font-bold tracking-tight sm:text-2xl">

                    @if ($isBorrower)
                        Ready for your next project?
                    @else
                        Your laboratory at a glance
                    @endif

                </h2>

                <p class="mt-2 max-w-xl text-sm leading-relaxed text-white">

                    @if ($isBorrower)
                        Explore available laboratory equipment,
                        manage your reservations and stay updated
                        on your borrowing activity.
                    @else
                        Keep track of equipment availability,
                        reservation requests and maintenance activity.
                    @endif

                </p>

                <a
                    href="{{ route('equipment.index') }}"
                    class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-lg border border-white bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-900 hover:underline focus-visible:outline-white">

                    <svg
                        class="h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        focusable="false">

                        <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                        <path d="M3 8v8l9 5 9-5V8"/>
                        <path d="M12 13v8"/>

                    </svg>

                    Browse equipment

                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        focusable="false">

                        <path d="M5 12h14 M12 5l7 7-7 7"/>

                    </svg>

                </a>

            </div>

        </section>

        {{-- Statistics section --}}

        <section aria-labelledby="overview-heading">

            <div class="mb-4">

                <h2
                    id="overview-heading"
                    class="text-lg font-bold text-slate-950">

                    Overview

                </h2>

                <p class="mt-1 text-sm text-slate-600">
                    Your current activity and equipment statistics.
                </p>

            </div>

            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">

                @foreach ($stats as $label => $value)

                    <x-card>

                        <div class="flex items-start justify-between gap-3">

                            <div class="min-w-0">

                                <dt class="stat-label break-words">
                                    {{ $label }}
                                </dt>

                                <dd class="stat-number">
                                    {{ $value }}
                                </dd>

                            </div>

                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"
                                aria-hidden="true">

                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    focusable="false">

                                    <path d="{{ $statIcons[strtolower($label)] ?? 'M3 3v18h18 M7 14l4-4 4 4 6-6' }}"/>

                                </svg>

                            </span>

                        </div>

                    </x-card>

                @endforeach

            </dl>

        </section>

        {{-- Main dashboard content --}}

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">

            {{-- Recent reservations --}}

            <x-card>

                <section aria-labelledby="recent-reservations-heading">

                    <div class="mb-4">

                        <h2
                            id="recent-reservations-heading"
                            class="text-lg font-bold text-slate-950">

                            Recent reservations

                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Latest reservation and borrowing activity.
                        </p>

                    </div>

                    <div class="divide-y divide-slate-200">

                        @forelse ($recent as $reservation)

                            <div class="flex flex-wrap items-center justify-between gap-3 py-4">

                                <div class="flex min-w-0 items-start gap-3">

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                                        aria-hidden="true">

                                        <svg
                                            class="h-5 w-5"
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

                                        <a
                                            href="{{ route('reservations.show', $reservation) }}"
                                            class="break-words text-sm font-semibold text-blue-700 underline decoration-transparent underline-offset-4 hover:decoration-current focus-visible:decoration-current">

                                            {{ $reservation->equipment->name }}

                                        </a>

                                        <p class="mt-1 text-xs text-slate-600">

                                            <time datetime="{{ $reservation->starts_at->toIso8601String() }}">
                                                {{ $reservation->starts_at->format('M j, Y g:i A') }}
                                            </time>

                                        </p>

                                        @if ($isManager)

                                            <p class="mt-1 break-words text-xs text-slate-600">

                                                Borrower:
                                                {{ $reservation->user->name }}

                                            </p>

                                        @endif

                                    </div>

                                </div>

                                <div class="flex flex-wrap items-center gap-2">

                                    <x-badge :status="$reservation->status" />

                                    @if (
                                        $reservation->status === \App\Enums\ReservationStatus::CheckedOut
                                        && $reservation->ends_at->isPast()
                                    )

                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">

                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true">

                                                <path d="M12 3 2 21h20L12 3z M12 9v4 M12 17h.01"/>

                                            </svg>

                                            Overdue

                                        </span>

                                    @endif

                                </div>

                            </div>

                        @empty

                            <x-empty-state>
                                There is no reservation activity yet.
                            </x-empty-state>

                        @endforelse

                    </div>

                </section>

            </x-card>

            {{-- Quick actions --}}

            <x-card>

                <section aria-labelledby="quick-actions-heading">

                    <div class="mb-4">

                        <h2
                            id="quick-actions-heading"
                            class="text-lg font-bold text-slate-950">

                            Quick actions

                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Easily access frequently used tools.
                        </p>

                    </div>

                    <ul class="space-y-3">

                        @foreach ($quickActions as $action)

                            <li>

                                <a
                                    href="{{ $action['url'] }}"
                                    class="group flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 hover:bg-slate-100">

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                                        aria-hidden="true">

                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.75"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            focusable="false">

                                            <path d="{{ $action['icon'] }}"/>

                                        </svg>

                                    </span>

                                    <span class="min-w-0 flex-1">

                                        <span class="block break-words text-sm font-semibold text-slate-900 group-hover:underline">
                                            {{ $action['label'] }}
                                        </span>

                                        <span class="mt-1 block text-xs text-slate-600">
                                            {{ $action['description'] }}
                                        </span>

                                    </span>

                                    <svg
                                        class="h-4 w-4 shrink-0 text-slate-500"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true">

                                        <path d="m9 18 6-6-6-6"/>

                                    </svg>

                                </a>

                            </li>

                        @endforeach

                    </ul>

                </section>

            </x-card>

        </div>

        {{-- Borrower dashboard sections --}}

        @if ($isBorrower)

            {{-- Required documents --}}

            <x-card>

                <section aria-labelledby="documents-heading">

                    <div class="flex flex-wrap items-center justify-between gap-3">

                        <div class="flex min-w-0 items-center gap-3">

                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"
                                aria-hidden="true">

                                <svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                    stroke-linecap="round"
                                    stroke-linejoin="round">

                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <path d="M14 2v6h6 M8 13h8 M8 17h8"/>

                                </svg>

                            </span>

                            <div class="min-w-0">

                                <h2
                                    id="documents-heading"
                                    class="text-lg font-bold text-slate-950">

                                    Required documents

                                </h2>

                                <p class="mt-1 text-sm text-slate-600">
                                    Complete the requirements for your reservations.
                                </p>

                            </div>

                        </div>

                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">

                            {{ $documentAcknowledged }}
                            of
                            {{ $documentTotal }}
                            acknowledged

                        </span>

                    </div>

                    <progress
                        class="mt-5 h-3 w-full accent-blue-700"
                        value="{{ $documentAcknowledged }}"
                        max="{{ max(1, $documentTotal) }}"
                        aria-label="Required document acknowledgement progress"
                        aria-valuetext="{{ $documentAcknowledged }} of {{ $documentTotal }} documents acknowledged">

                        {{ $documentAcknowledged }}
                        of
                        {{ $documentTotal }}

                    </progress>

                    <div class="mt-4 space-y-3">

                        @forelse ($pendingDocuments as $document)

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">

                                <a
                                    href="{{ route('documents.checklist') }}"
                                    class="break-words font-semibold text-blue-700 underline underline-offset-4">

                                    {{ $document->title }}

                                </a>

                                <p class="mt-1 text-slate-600">
                                    Required for {{ $document->equipment->name }}
                                </p>

                            </div>

                        @empty

                            <div class="flex items-start gap-3 rounded-lg bg-green-50 p-4 text-green-800">

                                <svg
                                    class="h-5 w-5 shrink-0"
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

                                <p class="text-sm font-medium">
                                    All required documents for your open
                                    reservations are acknowledged.
                                </p>

                            </div>

                        @endforelse

                    </div>

                    <a
                        href="{{ route('documents.checklist') }}"
                        class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">

                        Open document checklist

                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true">

                            <path d="M5 12h14 M12 5l7 7-7 7"/>

                        </svg>

                    </a>

                </section>

            </x-card>

            {{-- Upcoming and active reservations --}}

            <div class="grid items-start gap-5 lg:grid-cols-2">

                {{-- Upcoming reservations --}}

                <x-card>

                    <section aria-labelledby="upcoming-heading">

                        <div class="mb-4">

                            <h2
                                id="upcoming-heading"
                                class="text-lg font-bold text-slate-950">

                                Upcoming reservations

                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Your scheduled equipment reservations.
                            </p>

                        </div>

                        <div class="space-y-3">

                            @forelse ($upcoming as $reservation)

                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                                    <a
                                        href="{{ route('reservations.show', $reservation) }}"
                                        class="break-words text-sm font-semibold text-blue-700 underline underline-offset-4">

                                        {{ $reservation->equipment->name }}

                                    </a>

                                    <p class="mt-2 text-xs text-slate-600">

                                        Scheduled for

                                        <time datetime="{{ $reservation->starts_at->toIso8601String() }}">
                                            {{ $reservation->starts_at->format('M j, Y g:i A') }}
                                        </time>

                                    </p>

                                    <div class="mt-3">
                                        <x-badge :status="$reservation->status" />
                                    </div>

                                </div>

                            @empty

                                <x-empty-state>
                                    You have no upcoming approved reservations.
                                </x-empty-state>

                            @endforelse

                        </div>

                    </section>

                </x-card>

                {{-- Active checkouts --}}

                <x-card>

                    <section aria-labelledby="active-checkouts-heading">

                        <div class="mb-4">

                            <h2
                                id="active-checkouts-heading"
                                class="text-lg font-bold text-slate-950">

                                Active checkouts

                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Equipment currently borrowed by you.
                            </p>

                        </div>

                        <div class="space-y-3">

                            @forelse ($active as $reservation)

                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                                    <a
                                        href="{{ route('reservations.show', $reservation) }}"
                                        class="break-words text-sm font-semibold text-blue-700 underline underline-offset-4">

                                        {{ $reservation->equipment->name }}

                                    </a>

                                    <p class="mt-2 text-xs text-slate-600">

                                        Return by

                                        <time datetime="{{ $reservation->ends_at->toIso8601String() }}">
                                            {{ $reservation->ends_at->format('M j, Y g:i A') }}
                                        </time>

                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-2">

                                        <x-badge :status="$reservation->status" />

                                        @if ($reservation->ends_at->isPast())

                                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                Overdue
                                            </span>

                                        @endif

                                    </div>

                                </div>

                            @empty

                                <x-empty-state>
                                    You have no active checkouts.
                                </x-empty-state>

                            @endforelse

                        </div>

                    </section>

                </x-card>

            </div>

        @endif

        {{-- Lab manager dashboard --}}

        @if ($isManager)

            <x-card>

                <section aria-labelledby="maintenance-heading">

                    <div class="mb-4">

                        <h2
                            id="maintenance-heading"
                            class="text-lg font-bold text-slate-950">

                            Recent maintenance

                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Monitor equipment issues and repair activity.
                        </p>

                    </div>

                    <div class="divide-y divide-slate-200">

                        @forelse ($recentMaintenance as $log)

                            <div class="flex flex-wrap items-center justify-between gap-3 py-4">

                                <div class="min-w-0">

                                    <a
                                        href="{{ route('equipment.show', $log->equipment) }}"
                                        class="break-words text-sm font-semibold text-blue-700 underline underline-offset-4">

                                        {{ $log->equipment->name }}

                                    </a>

                                    <p class="mt-1 break-words text-sm text-slate-600">
                                        {{ $log->issue }}
                                    </p>

                                </div>

                                <x-badge :status="$log->resolved_at ? 'completed' : 'under_repair'" />

                            </div>

                        @empty

                            <x-empty-state>
                                No maintenance activity yet.
                            </x-empty-state>

                        @endforelse

                    </div>

                </section>

            </x-card>

        @endif

    </div>

</x-app-layout>
