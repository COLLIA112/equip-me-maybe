
{{-- Success or status message --}}

@if (session('status'))

    <div
        role="status"
        aria-live="polite"
        aria-atomic="true"
        data-flash
        class="ui-flash mb-5 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">

        <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100"
            aria-hidden="true">

            <svg
                class="h-6 w-6"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round">

                <circle cx="12" cy="12" r="9"/>
                <path d="m8 12 3 3 5-6"/>

            </svg>

        </span>

        <div class="min-w-0 flex-1">

            <p class="text-sm font-bold">
                Status update
            </p>

            <p class="mt-1 break-words text-sm leading-relaxed">
                {{ session('status') }}
            </p>

        </div>

        <button
            type="button"
            data-dismiss-flash
            aria-label="Dismiss notification"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg hover:bg-black/5">

            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false">

                <path d="M6 6l12 12M18 6L6 18"/>

            </svg>

        </button>

    </div>

@endif

{{-- Validation errors --}}

@if ($errors->any())

    <div
        role="alert"
        aria-atomic="true"
        class="ui-flash mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">

        <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100"
            aria-hidden="true">

            <svg
                class="h-6 w-6"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round">

                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8v4 M12 16h.01"/>

            </svg>

        </span>

        <div class="min-w-0 flex-1">

            <p class="text-sm font-bold">
                Please review the following errors
            </p>

            <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm leading-relaxed">

                @foreach ($errors->all() as $error)

                    <li class="break-words">
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    </div>

@endif
