@php $canUseWishlist = auth()->user()->isBorrower(); @endphp

<div class="responsive-table rounded-lg border border-slate-200 bg-white" role="region" aria-label="Equipment units" tabindex="0">
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
                        <a href="{{ route('equipment.show', $item) }}" class="inline-flex min-h-11 items-center text-blue-800 underline underline-offset-4">{{ $item->name }}</a>
                    </th>
                    <td class="px-4 py-3 tabular-nums">{{ $item->serial_number }}</td>
                    <td class="px-4 py-3">{{ $item->lab->name }}</td>
                    <td class="px-4 py-3">{{ $item->lab->department->name }}</td>
                    <td class="px-4 py-3"><x-availability-glance :equipment="$item" /></td>
                    @if ($canUseWishlist)
                        <td class="px-4 py-3">
                            @include('equipment._wishlist-action', ['item' => $item, 'wishlisted' => in_array($item->id, $wishlistIds)])
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
