
@props(['status'])

@php

    $value = $status instanceof \BackedEnum
        ? $status->value
        : (string) $status;

    /*
    |--------------------------------------------------------------------------
    | Status colors
    |--------------------------------------------------------------------------
    */

    $colors = [

        'available' => 'bg-green-100 text-green-800',

        'reserved' => 'bg-blue-100 text-blue-800',

        'under_repair' => 'bg-red-100 text-red-800',

        'pending' => 'bg-orange-100 text-orange-900',

        'approved' => 'bg-blue-100 text-blue-800',

        'checked_out' => 'bg-blue-100 text-blue-800',

        'returned' => 'bg-slate-200 text-slate-800',

        'completed' => 'bg-green-100 text-green-800',

        'rejected' => 'bg-red-100 text-red-800',

        'cancelled' => 'bg-slate-200 text-slate-700',

    ];

    /*
    |--------------------------------------------------------------------------
    | Decorative status icons
    |--------------------------------------------------------------------------
    */

    $icons = [

        'available' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M8 12l3 3 5-6',

        'reserved' => 'M8 2v4 M16 2v4 M3 10h18 M3 4h18v17H3z',

        'under_repair' => 'M14 6l4 4 M5 19l9-9 M16 3a5 5 0 0 0-6 6L3 16v5h5l7-7a5 5 0 0 0 6-6l-4 3-4-4 3-4z',

        'pending' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 7v5l3 2',

        'approved' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M8 12l3 3 5-6',

        'checked_out' => 'M12 3 3 8l9 5 9-5-9-5z M3 8v8l9 5 9-5V8 M12 13v8',

        'returned' => 'M9 10H20v5a4 4 0 0 1-4 4H9 M9 4l-6 6 6 6',

        'completed' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M8 12l3 3 5-6',

        'rejected' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M9 9l6 6 M15 9l-6 6',

        'cancelled' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M6 6l12 12',

    ];

    $label = ucfirst(str_replace('_', ' ', $value));

@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex max-w-full items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold leading-relaxed '
        . ($colors[$value] ?? 'bg-slate-100 text-slate-700')
]) }}>

    <svg class="h-3.5 w-3.5 shrink-0"
         viewBox="0 0 24 24"
         fill="none"
         stroke="currentColor"
         stroke-width="2"
         stroke-linecap="round"
         stroke-linejoin="round"
         aria-hidden="true"
         focusable="false">

        <path d="{{ $icons[$value] ?? 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 8h.01 M12 12v4' }}"/>

    </svg>

    <span class="break-words">
        {{ $label }}
    </span>

</span>
