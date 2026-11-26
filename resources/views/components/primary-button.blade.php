
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'ui-button ui-button-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-transparent bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800 active:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-60'
]) }}>
    {{ $slot }}
</button>
