
<form method="POST"
      action="{{ route($wishlisted ? 'wishlist.destroy' : 'wishlist.store', $item) }}">

    @csrf

    @if ($wishlisted)
        @method('DELETE')
    @endif

    <button type="submit"
            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-semibold {{ $wishlisted ? 'text-red-700' : 'text-blue-700' }} hover:bg-slate-100"
            aria-label="{{ $wishlisted
                ? 'Remove from wishlist: '.$item->name
                : 'Save to wishlist: '.$item->name }}">

        <svg class="h-5 w-5 shrink-0"
             viewBox="0 0 24 24"
             fill="{{ $wishlisted ? 'currentColor' : 'none' }}"
             stroke="currentColor"
             stroke-width="1.75"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true"
             focusable="false">

            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>

        </svg>

        <span>
            {{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}
        </span>

    </button>

</form>
