
<dialog
    id="confirm-action"
    x-data="confirmation"
    aria-modal="true"
    aria-labelledby="confirm-action-title"
    aria-describedby="confirm-action-text"
    @click.self="cancel"
    @close="reset"
    class="ui-confirm-dialog m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl backdrop:bg-slate-950/60">

    {{-- Confirmation header --}}

    <div class="flex items-start justify-between gap-4">

        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800"
             aria-hidden="true">

            <svg
                class="h-6 w-6"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
                focusable="false">

                <path d="M12 3 2 21h20L12 3z"/>
                <path d="M12 9v4 M12 17h.01"/>

            </svg>

        </div>

        <button
            type="button"
            @click="cancel"
            aria-label="Close confirmation dialog"
            class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100">

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

    {{-- Confirmation message --}}

    <div class="mt-5">

        <h2
            id="confirm-action-title"
            class="text-xl font-bold tracking-tight text-slate-950">

            Confirm action

        </h2>

        <p
            id="confirm-action-text"
            class="mt-3 break-words text-sm leading-relaxed text-slate-700"
            x-text="message">
        </p>

    </div>

    {{-- Confirmation actions --}}

    <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">

        <x-secondary-button
            autofocus
            @click="cancel">

            Go back

        </x-secondary-button>

        <x-danger-button
            type="button"
            @click="confirm">

            Confirm

        </x-danger-button>

    </div>

</dialog>
