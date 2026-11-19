<form method="POST" action="{{ route($wishlisted ? 'wishlist.destroy' : 'wishlist.store', $item) }}">
    @csrf
    @if ($wishlisted) @method('DELETE') @endif
    <button class="text-sm font-semibold text-blue-700 underline" aria-label="{{ $wishlisted ? 'Remove from wishlist: '.$item->name : 'Save to wishlist: '.$item->name }}">{{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}</button>
</form>
