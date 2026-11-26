
@props([
    'value' => null
])

<label {{ $attributes->merge([
    'class' => 'mb-2 block text-sm font-semibold leading-relaxed text-slate-700'
]) }}>
    {{ $value ?? $slot }}
</label>
