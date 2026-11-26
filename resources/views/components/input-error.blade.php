
@props([
    'messages' => []
])

@if ($messages)

    <ul {{ $attributes->merge([
        'role' => 'alert',
        'class' => 'ui-input-error mt-2 space-y-2 text-sm font-medium text-red-700'
    ]) }}>

        @foreach ((array) $messages as $message)

            <li class="flex items-start gap-2">

                <svg
                    class="mt-0.5 h-4 w-4 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    focusable="false">

                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 8v4 M12 16h.01"/>

                </svg>

                <span class="break-words">
                    {{ $message }}
                </span>

            </li>

        @endforeach

    </ul>

@endif
