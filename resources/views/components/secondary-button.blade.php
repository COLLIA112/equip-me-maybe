
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'ui-button ui-button-secondary inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60'
]) }}>
    {{ $slot }}
</button>
