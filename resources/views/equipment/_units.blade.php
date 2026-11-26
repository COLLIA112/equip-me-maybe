
@php
    $canUseWishlist = auth()->user()->isBorrower();
@endphp

<p id="equipment-units-help"
   class="mb-4 text-sm text-slate-600">

    Select an equipment name to view its details and availability.
    Scroll the table horizontally if needed.

</p>

<div class="responsive-table ui-table rounded-xl border border-slate-200 bg-white"
     role="region"
     aria-label="Equipment units"
     aria-describedby="equipment-units-help"
     tabindex="0">

    <table class="w-full text-left text-sm">

        <caption class="sr-only">
            Matching equipment units
        </caption>

        <thead class="border-b border-slate-200 bg-slate-100 text-slate-700">

            <tr>

                <th scope="col" class="px-4 py-4">
                    Equipment
                </th>

                <th scope="col" class="px-4 py-4">
                    Serial number
                </th>

                <th scope="col" class="px-4 py-4">
                    Laboratory
                </th>

                <th scope="col" class="px-4 py-4">
                    Department
                </th>

                <th scope="col" class="px-4 py-4">
                    Availability
                </th>

                @if ($canUseWishlist)

                    <th scope="col" class="px-4 py-4">
                        Wishlist
                    </th>

                @endif

            </tr>

        </thead>

        <tbody>

            @foreach ($equipment as $item)

                <tr class="border-t border-slate-200">

                    <th scope="row"
                        class="px-4 py-4 text-left font-medium">

                        <div class="flex items-center gap-3">

                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                                  aria-hidden="true">

                                <svg class="h-5 w-5"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.75"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                                    <path d="M3 8v8l9 5 9-5V8"/>
                                    <path d="M12 13v8"/>

                                </svg>

                            </span>

                            <a href="{{ route('equipment.show', $item) }}"
                               class="inline-flex min-h-11 items-center font-semibold text-blue-700 underline underline-offset-4">

                                {{ $item->name }}

                            </a>

                        </div>

                    </th>

                    <td class="px-4 py-4 tabular-nums">

                        {{ $item->serial_number }}

                    </td>

                    <td class="px-4 py-4">

                        {{ $item->lab->name }}

                    </td>

                    <td class="px-4 py-4">

                        {{ $item->lab->department->name }}

                    </td>

                    <td class="px-4 py-4">

                        <x-availability-glance :equipment="$item" />

                    </td>

                    @if ($canUseWishlist)

                        <td class="px-4 py-4">

                            @include('equipment._wishlist-action', [
                                'item' => $item,
                                'wishlisted' => in_array($item->id, $wishlistIds)
                            ])

                        </td>

                    @endif

                </tr>

            @endforeach

        </tbody>

    </table>

</div>
