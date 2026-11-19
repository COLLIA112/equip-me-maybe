<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">{{ $model ?? 'Equipment catalog' }}</h1>
            @if (auth()->user()->isBorrower())
                <a href="{{ route('wishlist.index') }}" class="text-sm font-semibold text-blue-700 underline">My wishlist</a>
            @endif
            @can('create', \App\Models\Equipment::class)
                <a href="{{ route('equipment.create') }}" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add equipment</a>
            @endcan
        </div>
    </x-slot>

    @if ($model !== null)
        <a href="{{ route('equipment.index') }}" class="mb-4 inline-block text-sm font-semibold text-blue-700 underline">Back to equipment catalog</a>
    @endif

    <form method="GET" data-instant-filters class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-6">
        @if ($model !== null)
            <input type="hidden" name="model" value="{{ $model }}">
        @endif
        <input aria-label="Search by model, unit number, serial number, QR code, lab, or department" name="q" data-filter-search value="{{ $filters['q'] ?? '' }}" placeholder="Search model, unit, serial, lab, or department" class="rounded-md border-slate-300 text-sm xl:col-span-2">
        <select onchange="this.form.requestSubmit()" aria-label="Department" name="department_id" class="rounded-md border-slate-300 text-sm">
            <option value="">All departments</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <select onchange="this.form.requestSubmit()" aria-label="Lab" name="lab_id" class="rounded-md border-slate-300 text-sm">
            <option value="">All labs</option>
            @foreach ($labs as $l)<option value="{{ $l->id }}" @selected(($filters['lab_id'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
        </select>
        <select onchange="this.form.requestSubmit()" aria-label="Equipment status" name="status" class="rounded-md border-slate-300 text-sm">
            <option value="">Any status</option>
            @foreach (\App\Enums\EquipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
        </select>
        <select onchange="this.form.requestSubmit()" aria-label="Equipment tag" name="tag" class="rounded-md border-slate-300 text-sm">
            <option value="">Any tag</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected(($filters['tag'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <div class="sm:col-span-2 xl:col-span-3">
            <label for="catalog-sort" class="block text-sm font-medium">Sort by</label>
            <select id="catalog-sort" name="sort" onchange="this.form.requestSubmit()" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                @foreach (['name' => 'Name', 'serial_number' => 'Serial number', 'status' => 'Status', 'created_at' => 'Date added'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="sm:col-span-2 xl:col-span-3">
            <label for="catalog-direction" class="block text-sm font-medium">Order</label>
            <select id="catalog-direction" name="direction" onchange="this.form.requestSubmit()" class="mt-1 w-full rounded-md border-slate-300 text-sm"><option value="asc" @selected($direction === 'asc')>Ascending</option><option value="desc" @selected($direction === 'desc')>Descending</option></select>
        </div>
        <div class="flex items-center gap-4 sm:col-span-2 xl:col-span-6">
            <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
            @if (count($filters))
                <a href="{{ route('equipment.index', $model === null ? ['clear' => 1] : ['model' => $model]) }}" class="text-sm font-semibold text-blue-700 hover:underline">Clear filter</a>
            @endif
        </div>
    </form>

    <x-list-summary :paginator="$equipment" />
    @if ($equipment->isEmpty())
        <x-card class="text-center text-sm text-slate-500">
            {{ count($filters) || $model !== null ? 'No equipment matches your filters. Clear the filters to see all available items.' : 'No equipment has been added yet. Please check back later.' }}
        </x-card>
    @elseif ($showUnits)
        @include('equipment._units')
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($equipment as $group)
                @php
                    $item = $group['unit'];
                    $url = $item
                        ? route('equipment.show', $item)
                        : route('equipment.index', ['model' => $group['name']] + $modelFilters);
                @endphp
                <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    @if ($group['photo_url'])
                        <img src="{{ $group['photo_url'] }}" alt="" class="h-36 w-full object-cover">
                    @else
                        <div class="flex h-36 items-center justify-center bg-slate-100 text-sm text-slate-600">No photo</div>
                    @endif
                    <div class="p-4">
                        <h2 class="font-medium">
                            <a href="{{ $url }}" class="text-blue-800 hover:underline">{{ $item?->name ?? $group['name'] }}</a>
                        </h2>
                        @if ($item)
                            <div class="mt-2"><x-availability-glance :equipment="$item" /></div>
                            @if (auth()->user()->isBorrower())
                                <div class="mt-2">
                                    @include('equipment._wishlist-action', ['item' => $item, 'wishlisted' => in_array($item->id, $wishlistIds)])
                                </div>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-slate-600">Quantity: {{ $group['quantity'] }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-700">Available: {{ $group['available'] }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="mt-5">{{ $equipment->links() }}</div>
</x-app-layout>
