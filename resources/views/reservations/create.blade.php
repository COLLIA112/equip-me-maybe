
<x-app-layout>

    <x-slot name="header">
        <div>
            <p class="mb-1 text-sm font-semibold text-blue-700">
                New Reservation
            </p>

            <h1 class="page-title">
                {{ isset($prefill) ? 'Reserve again: ' : 'Reserve ' }}
                {{ $equipment->name }}
            </h1>

            <p class="mt-2 text-sm text-slate-600">
                Choose your preferred borrowing and return schedule.
            </p>
        </div>
    </x-slot>

    <a href="{{ route('equipment.show', $equipment) }}"
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

        Back to equipment details

    </a>

    <div class="grid items-start gap-6 lg:grid-cols-2">

        {{-- Reservation form --}}

        <x-card>

            <h2 class="text-lg font-bold text-slate-950">
                Reservation schedule
            </h2>

            <p class="mb-5 mt-2 text-sm text-slate-600">
                Complete the required fields to submit your reservation.
            </p>

            <form method="POST"
                  action="{{ route('reservations.store', $equipment) }}"
                  class="space-y-5"
                  data-reservation-dates
                  data-reservation-timezone="{{ config('app.timezone') }}">

                @csrf

                {{-- Start date --}}

                <div>

                    <label for="starts_at"
                           class="mb-2 block text-sm font-semibold">

                        Start date and time
                        <span class="text-xs text-slate-600">(required)</span>

                    </label>

                    <input type="datetime-local"
                           id="starts_at"
                           name="starts_at"
                           value="{{ old('starts_at', $prefill['starts_at'] ?? '') }}"
                           min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}"
                           aria-describedby="reservation-date-help reservation-conflict @error('starts_at') starts_at-error @enderror"
                           @error('starts_at') aria-invalid="true" @enderror
                           required
                           class="min-h-12 min-w-0 w-full rounded-lg border-slate-300 text-base tabular-nums">

                    @error('starts_at')

                        <p id="starts_at-error"
                           role="alert"
                           class="mt-2 text-sm font-medium text-red-700">

                            {{ $message }}

                        </p>

                    @enderror

                </div>

                {{-- End date --}}

                <div>

                    <label for="ends_at"
                           class="mb-2 block text-sm font-semibold">

                        End date and time
                        <span class="text-xs text-slate-600">(required)</span>

                    </label>

                    <input type="datetime-local"
                           id="ends_at"
                           name="ends_at"
                           value="{{ old('ends_at', $prefill['ends_at'] ?? '') }}"
                           min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}"
                           aria-describedby="reservation-date-help reservation-conflict @error('ends_at') ends_at-error @enderror"
                           @error('ends_at') aria-invalid="true" @enderror
                           required
                           class="min-h-12 min-w-0 w-full rounded-lg border-slate-300 text-base tabular-nums">

                    @error('ends_at')

                        <p id="ends_at-error"
                           role="alert"
                           class="mt-2 text-sm font-medium text-red-700">

                            {{ $message }}

                        </p>

                    @enderror

                </div>

                {{-- Schedule information --}}

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                    <div class="flex items-start gap-3">

                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-700"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">

                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 11v5 M12 8h.01"/>

                        </svg>

                        <div>

                            <h3 class="text-sm font-semibold">
                                Schedule information
                            </h3>

                            <p id="reservation-date-help"
                               class="mt-1 text-sm leading-relaxed text-slate-600">

                                Times are shown in {{ config('app.timezone') }}.
                                End defaults to one hour after start.
                                Reservation times are checked again when you submit.

                            </p>

                        </div>

                    </div>

                </div>

                <p id="reservation-conflict"
                   role="status"
                   aria-live="polite"
                   class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-relaxed tabular-nums text-amber-900">
                </p>

                {{-- Preserve reservation conflict detection --}}

                @php

                    $bookingWindows = $booked->map(fn ($booking) => [
                        'start' => $booking->starts_at->format('Y-m-d\TH:i'),
                        'end' => $booking->ends_at->format('Y-m-d\TH:i'),
                        'label' => $booking->starts_at->format('M j, Y g:i A')
                            .' to '.
                            $booking->ends_at->format('M j, Y g:i A')
                    ])->values();

                @endphp

                <script type="application/json" data-booked-windows>
                    @json($bookingWindows)
                </script>

                {{-- Faculty class session --}}

                @if (auth()->user()->hasRole(\App\Enums\Role::Faculty))

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                        <label for="is_class_session"
                               class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold">

                            <input type="checkbox"
                                   id="is_class_session"
                                   name="is_class_session"
                                   value="1"
                                   aria-describedby="faculty-session-help"
                                   @checked(old('is_class_session'))
                                   class="h-5 w-5 rounded border-slate-400">

                            Faculty class session

                        </label>

                        <p id="faculty-session-help"
                           class="mt-2 text-sm leading-relaxed text-slate-600">

                            A class-session request displaces overlapping
                            pending student requests. Approved and checked-out
                            bookings still block the time slot.

                        </p>

                    </div>

                @endif

                {{-- Required documents reminder --}}

                <div class="rounded-xl border border-slate-200 bg-blue-50 p-4">

                    <div class="flex items-start gap-3">

                        <svg class="h-5 w-5 shrink-0 text-blue-700"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.75"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">

                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6 M8 13h8 M8 17h8"/>

                        </svg>

                        <div>

                            <h3 class="text-sm font-bold text-blue-800">
                                Required documents
                            </h3>

                            <p class="mt-1 text-sm leading-relaxed text-slate-700">

                                Download and acknowledge the required documents
                                before the lab manager can approve your request.

                            </p>

                        </div>

                    </div>

                </div>

                {{-- Form actions --}}

                <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5">

                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">

                        Request reservation

                        <svg class="h-4 w-4"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">

                            <path d="M5 12h14 M12 5l7 7-7 7"/>

                        </svg>

                    </button>

                    <a href="{{ route('equipment.show', $equipment) }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold hover:bg-slate-100">

                        Cancel

                    </a>

                </div>

            </form>

        </x-card>

        {{-- Booking schedule --}}

        <x-card>

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

                <div>

                    <h2 class="text-lg font-bold text-slate-950">
                        Already booked
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">

                        Review existing booking windows before choosing a schedule.

                    </p>

                </div>

            </div>

            <div class="mt-5 space-y-3">

                @forelse ($booked as $b)

                    <div class="equipment-info-item">

                        <p class="text-xs font-semibold text-slate-600">
                            Booking starts
                        </p>

                        <p class="mt-1 text-sm font-semibold">

                            <time datetime="{{ $b->starts_at->toIso8601String() }}">
                                {{ $b->starts_at->format('M j, Y g:i A') }}
                            </time>

                        </p>

                        <div class="my-3 border-t border-slate-200"></div>

                        <p class="text-xs font-semibold text-slate-600">
                            Booking ends
                        </p>

                        <p class="mt-1 text-sm font-semibold">

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

        </x-card>

    </div>

</x-app-layout>
