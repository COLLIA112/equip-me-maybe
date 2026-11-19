<form method="POST" action="{{ route($wishlisted ? 'wishlist.destroy' : 'wishlist.store', $item) }}">
    @csrf
    @if ($wishlisted) @method('DELETE') @endif
    <button type="submit" class="min-h-11 rounded-md border border-slate-300 bg-white px-3 py-2 text-left text-sm font-semibold text-blue-700 hover:bg-slate-50" aria-label="{{ $wishlisted ? 'Remove from wishlist: '.$item->name : 'Save to wishlist: '.$item->name }}">{{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}</button>
</form>
