<dialog id="confirm-action" x-data="confirmation" aria-labelledby="confirm-action-title" aria-describedby="confirm-action-text"
        @click.self="cancel" @close="reset" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-slate-200 p-6 shadow-xl backdrop:bg-slate-950/60">
    <h2 id="confirm-action-title" class="text-lg font-bold text-slate-950">Confirm action</h2>
    <p id="confirm-action-text" class="mt-2 break-words text-sm text-slate-700" x-text="message"></p>
    <div class="mt-6 flex flex-wrap justify-end gap-2">
        <x-secondary-button autofocus @click="cancel">Go back</x-secondary-button>
        <x-danger-button type="button" @click="confirm">Confirm</x-danger-button>
    </div>
</dialog>
