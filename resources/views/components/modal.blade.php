
@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'label' => null
])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<dialog

    x-data="{
        show: @js($show),
        modalName: @js($name),
        previousFocus: null,

        init() {

            this.$watch('show', value => {

                if (value) {
                    this.openDialog();
                } else {
                    this.closeDialog();
                }

            });

            if (this.show) {
                this.$nextTick(() => this.openDialog());
            }

        },

        openDialog() {

            if (this.$el.open) return;

            this.previousFocus = document.activeElement;

            this.$el.showModal();

            document.body.classList.add('overflow-y-hidden');

        },

        closeDialog() {

            if (this.$el.open) {
                this.$el.close();
            }

        },

        finishClose() {

            this.show = false;

            if (!document.querySelector('dialog[open]')) {
                document.body.classList.remove('overflow-y-hidden');
            }

            if (this.previousFocus &&
                this.previousFocus.isConnected) {

                this.previousFocus.focus();

            }

            this.previousFocus = null;

        }
    }"

    x-on:open-modal.window="
        if ($event.detail === modalName) {
            show = true
        }
    "

    x-on:close-modal.window="
        if ($event.detail === modalName) {
            show = false
        }
    "

    x-on:close.stop="
        if ($event.target === $el) {
            finishClose()
        } else {
            show = false
        }
    "

    x-on:cancel.prevent="show = false"

    x-on:click.self="show = false"

    aria-modal="true"

    aria-label="{{ $label ?? ucfirst(str_replace(['-', '_'], ' ', $name)) }}"

    class="ui-modal fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl {{ $maxWidth }}"
>

    <div class="relative">

        <div class="flex justify-end px-4 pt-4">

            <button
                type="button"
                x-on:click="show = false"
                aria-label="Close dialog"
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

        <div>

            {{ $slot }}

        </div>

    </div>

</dialog>
