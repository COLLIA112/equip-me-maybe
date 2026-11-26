
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'ui-button ui-button-danger inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-transparent bg-red-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-red-800 active:bg-red-900 disabled:cursor-not-allowed disabled:opacity-60'
]) }}>
    {{ $slot }}
</button>
