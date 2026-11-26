
<x-app-layout>

    <x-slot name="header">

        <div class="flex w-full flex-wrap items-center justify-between gap-4">

            <div class="min-w-0">

                <p class="mb-1 text-sm font-semibold text-blue-700">
                    Equipment Details
                </p>

                <h1 class="page-title">
                    {{ $equipment->name }}
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    View equipment information, booking schedules and required documents.
                </p>

            </div>

            <div class="flex flex-wrap items-center gap-2">

                @if (auth()->user()->isBorrower())

                    @include('equipment._wishlist-action', [
                        'item' => $equipment
                    ])

                @endif

                @can('reserve_equipment')

                    @if ($equipment->status->blocksReservation())

                        <span class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-800">

                            <svg class="h-5 w-5 shrink-0"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">

                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 8v4 M12 16h.01"/>

                            </svg>

                            Under repair, cannot reserve

                        </span>

                    @else

                        <a href="{{ route('reservations.create', $equipment) }}"
                           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">

                            <svg class="h-5 w-5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.75"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">

                                <path d="M8 2v4 M16 2v4 M3 10h18 M3 4h18v17H3z"/>
                                <path d="M12 13v5 M9.5 15.5h5"/>

                            </svg>

                            Reserve equipment

                        </a>

                    @endif

                @endcan

                @can('update', $equipment)

                    <a href="{{ route('equipment.edit', $equipment) }}"
                       class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100">

                        Edit equipment

                    </a>

                    @can('create', [\App\Models\Document::class, $equipment])

                        <a href="{{ route('documents.index', $equipment) }}"
                           class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100">

                            Manage documents

                        </a>

                    @endcan

                @endcan

            </div>

        </div>

    </x-slot>

    {{-- Back navigation --}}

    <a href="{{ route('equipment.index') }}"
       class="mb-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-blue-700 underline underline-offset-4">

        <svg class="h-4 w-4"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">

            <path d="M19 12H5 M12 5l-7 7 7 7"/>

        </svg>

        Back to equipment catalog

    </a>

    <div class="grid items-start gap-6 lg:grid-cols-3">

        {{-- Equipment information --}}

        <x-card class="lg:col-span-2">

            <section aria-labelledby="equipment-information-heading">

                <div class="equipment-detail-media mb-6">

                    @if ($equipment->photo_url)

                        <img src="{{ $equipment->photo_url }}"
                             alt="{{ $equipment->name }}"
                             class="h-full w-full object-contain">

                    @else

                        <div class="flex flex-col items-center gap-3 text-slate-500">

                            <svg class="h-20 w-20 text-blue-700"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.25"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">

                                <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                                <path d="M3 8v8l9 5 9-5V8"/>
                                <path d="M12 13v8"/>

                            </svg>

                            <span class="text-sm">
                                No equipment image available
                            </span>

                        </div>

                    @endif

                </div>

                <h2 id="equipment-information-heading"
                    class="text-lg font-bold text-slate-950">

                    Equipment information

                </h2>

                <p class="mt-1 text-sm text-slate-600">
                    Identification, status and laboratory information.
                </p>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Available now
                        </dt>

                        <dd class="mt-2">
                            <x-availability-glance :equipment="$equipment" />
                        </dd>

                    </div>

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Current status
                        </dt>

                        <dd class="mt-2">
                            <x-badge :status="$equipment->status" />
                        </dd>

                    </div>

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Serial number
                        </dt>

                        <dd class="mt-2 break-words text-sm font-semibold tabular-nums text-slate-950">
                            {{ $equipment->serial_number }}
                        </dd>

                    </div>

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Laboratory
                        </dt>

                        <dd class="mt-2 break-words text-sm font-semibold text-slate-950">
                            {{ $equipment->lab->name }}
                        </dd>

                    </div>

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Department
                        </dt>

                        <dd class="mt-2 break-words text-sm font-semibold text-slate-950">
                            {{ $equipment->lab->department->name }}
                        </dd>

                    </div>

                    <div class="equipment-info-item">

                        <dt class="text-sm font-medium text-slate-600">
                            Equipment tags
                        </dt>

                        <dd class="mt-2 flex flex-wrap gap-2">

                            @forelse ($equipment->tags as $tag)

                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                                    {{ $tag->name }}
                                </span>

                            @empty

                                <span class="text-sm text-slate-600">
                                    No tags assigned
                                </span>

                            @endforelse

                        </dd>

                    </div>

                </dl>

                {{-- QR code --}}

                <div class="mt-6 border-t border-slate-200 pt-5">

                    <h3 class="text-base font-bold text-slate-950">
                        Equipment QR code
                    </h3>

                    <p class="mt-1 text-sm text-slate-600">
                        Use this code to identify the equipment.
                    </p>

                    <div class="mt-4">

                        <x-equipment-qr :code="$equipment->qr_code" />

                    </div>

                    @can('update', $equipment)

                        <a href="{{ route('equipment.qr-label', $equipment) }}"
                           class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                            <svg class="h-5 w-5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.75"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">

                                <path d="M6 9V3h12v6 M6 17H3V9h18v8h-3 M6 14h12v7H6z"/>
                                <path d="M17 12h1"/>

                            </svg>

                            Print QR label

                        </a>

                    @endcan

                </div>

            </section>

        </x-card>

        {{-- Booking schedule --}}

        <x-card>

            <section aria-labelledby="booked-windows-heading">

                <div class="flex items-start gap-3">

                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"
                          aria-hidden="true">

                        <svg class="h-6 w-6"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.75"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M8 2v4 M16 2v4 M3 10h18 M3 4h18v17H3z"/>

                        </svg>

                    </span>

                    <div class="min-w-0">

                        <h2 id="booked-windows-heading"
                            class="text-lg font-bold text-slate-950">

                            Booked windows

                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Upcoming reserved time slots.
                        </p>

                    </div>

                </div>

                <div class="mt-5 space-y-3">

                    @forelse ($booked as $b)

                        <div class="equipment-info-item">

                            <p class="text-xs font-semibold text-slate-600">
                                Booking starts
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-950">

                                <time datetime="{{ $b->starts_at->toIso8601String() }}">
                                    {{ $b->starts_at->format('M j, Y g:i A') }}
                                </time>

                            </p>

                            <div class="my-3 border-t border-slate-200"></div>

                            <p class="text-xs font-semibold text-slate-600">
                                Booking ends
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-950">

                                <time datetime="{{ $b->ends_at->toIso8601String() }}">
                                    {{ $b->ends_at->format('M j, Y g:i A') }}
                                </time>

                            </p>

                        </div>

                    @empty

                        <x-empty-state>
                            No upcoming bookings.
                        </x-empty-state>

                    @endforelse

                </div>

            </section>

        </x-card>

        {{-- Equipment documents --}}

        <x-card class="lg:col-span-3">

            <section aria-labelledby="equipment-documents-heading">

                <div class="mb-5 flex items-start gap-3">

                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"
                          aria-hidden="true">

                        <svg class="h-6 w-6"
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

                        <h2 id="equipment-documents-heading"
                            class="text-lg font-bold text-slate-950">

                            Equipment documents

                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Review equipment instructions and required safety documents.
                        </p>

                    </div>

                </div>

                @if (auth()->user()->isBorrower())

                    @php

                        $required = $equipment->documents->where(
                            'is_required',
                            true
                        );

                        $missingDownloads = $required->filter(
                            fn ($document) =>
                                ! $progress->get($document->id)?->pivot?->downloaded_at
                        );

                        $readCount = $required->filter(
                            fn ($document) =>
                                $progress->get($document->id)?->pivot?->acknowledged_at
                        )->count();

                    @endphp

                    @if ($required->isNotEmpty())

                        <div class="equipment-info-item mb-5">

                            <h3 class="text-base font-bold text-slate-950">
                                Required document progress
                            </h3>

                            <p class="mt-2 text-sm tabular-nums text-slate-600">

                                {{ $readCount }} of {{ $required->count() }}
                                required documents acknowledged

                            </p>

                            <progress class="mt-3 h-3 w-full accent-blue-700"
                                      value="{{ $readCount }}"
                                      max="{{ $required->count() }}"
                                      aria-label="Required document acknowledgement progress"
                                      aria-valuetext="{{ $readCount }} of {{ $required->count() }} required documents acknowledged">

                                {{ $readCount }} of {{ $required->count() }}

                            </progress>

                            <form method="POST"
                                  action="{{ route('equipment.documents.acknowledge-all', $equipment) }}"
                                  class="mt-4">

                                @csrf

                                <button type="submit"
                                        @disabled($missingDownloads->isNotEmpty() || $readCount === $required->count())
                                        aria-describedby="download-required-reason"
                                        class="min-h-11 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">

                                    Mark all as read

                                </button>

                                <p id="download-required-reason"
                                   class="mt-3 max-w-prose text-sm leading-relaxed text-slate-600">

                                    {{ $missingDownloads->isNotEmpty()
                                        ? 'Download every required PDF first: '.$missingDownloads->pluck('title')->implode(', ').'.'
                                        : ($readCount === $required->count()
                                            ? 'All required documents are acknowledged.'
                                            : 'All required PDFs have been downloaded. Confirm you have read them before marking all as read.') }}

                                </p>

                            </form>

                        </div>

                    @endif

                @endif

                <div class="space-y-3">

                    @forelse ($equipment->documents as $doc)

                        @php
                            $pivot = $progress->get($doc->id)?->pivot;
                        @endphp

                        <article class="equipment-info-item">

                            <div class="flex flex-wrap items-center justify-between gap-4">

                                <div class="flex min-w-0 items-start gap-3">

                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                                          aria-hidden="true">

                                        <svg class="h-5 w-5"
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

                                        <h3 class="break-words text-sm font-semibold text-slate-950">
                                            {{ $doc->title }}
                                        </h3>

                                        <p class="mt-1 text-xs text-slate-600">

                                            {{ ucfirst(str_replace('_', ' ', $doc->type)) }}

                                        </p>

                                        @if ($doc->is_required)

                                            <span class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">
                                                Required
                                            </span>

                                        @endif

                                    </div>

                                </div>

                                <div class="flex flex-wrap items-center gap-3">

                                    @if (auth()->user()->isBorrower())

                                        @if ($pivot?->acknowledged_at)

                                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                Acknowledged
                                            </span>

                                        @elseif ($pivot?->downloaded_at)

                                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                                                Downloaded, not acknowledged
                                            </span>

                                        @else

                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                Not downloaded
                                            </span>

                                        @endif

                                    @endif

                                    <a href="{{ route('documents.download', $doc) }}"
                                       aria-label="Download {{ $doc->title }}"
                                       class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                                        <svg class="h-4 w-4"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="2"
                                             stroke-linecap="round"
                                             stroke-linejoin="round"
                                             aria-hidden="true">

                                            <path d="M12 3v12 M7 10l5 5 5-5 M4 17v4h16v-4"/>

                                        </svg>

                                        Download

                                    </a>

                                </div>

                            </div>

                        </article>

                    @empty

                        <x-empty-state>
                            No documents uploaded for this item.
                        </x-empty-state>

                    @endforelse

                </div>

            </section>

        </x-card>

    </div>

    {{-- Existing availability calendar --}}

    <div class="mt-6">

        <x-equipment-week-calendar
            :equipment="$equipment"
            :week="$week"
            :bookings="$weekBookings"
        />

    </div>

</x-app-layout>
