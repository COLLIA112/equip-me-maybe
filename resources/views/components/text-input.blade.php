
@props([
    'disabled' => false
])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'ui-input min-h-11 max-w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-blue-700 focus:ring-2 focus:ring-blue-700 disabled:cursor-not-allowed disabled:opacity-60'
    ]) }}
>
