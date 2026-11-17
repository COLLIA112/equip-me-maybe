@php $canUseWishlist = auth()->user()->isBorrower(); @endphp

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Matching equipment units</caption>
        <thead class="border-b border-slate-200 bg-slate-100 text-slate-700">
            <tr>
                <th scope="col" class="px-4 py-3">Equipment</th>
                <th scope="col" class="px-4 py-3">Serial number</th>
                <th scope="col" class="px-4 py-3">Lab</th>
                <th scope="col" class="px-4 py-3">Department</th>
                <th scope="col" class="px-4 py-3">Availability</th>
                @if ($canUseWishlist)
                    <th scope="col" class="px-4 py-3">Wishlist</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($equipment as $item)
                <tr class="border-t border-slate-200">
                    <th scope="row" class="px-4 py-3 font-medium">
                        <a href="{{ route('equipment.show', $item) }}" class="text-blue-800 hover:underline">{{ $item->name }}</a>
                    </th>
                    <td class="px-4 py-3">{{ $item->serial_number }}</td>
                    <td class="px-4 py-3">{{ $item->lab->name }}</td>
                    <td class="px-4 py-3">{{ $item->lab->department->name }}</td>
                    <td class="px-4 py-3"><x-availability-glance :equipment="$item" /></td>
                    @if ($canUseWishlist)
                        @php $wishlisted = in_array($item->id, $wishlistIds); @endphp
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route($wishlisted ? 'wishlist.destroy' : 'wishlist.store', $item) }}">
                                @csrf
                                @if ($wishlisted) @method('DELETE') @endif
                                <button class="font-semibold text-blue-700 underline" aria-label="{{ $wishlisted ? 'Remove '.$item->name.' from wishlist' : 'Save '.$item->name.' to wishlist' }}">{{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
