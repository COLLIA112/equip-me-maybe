<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">{{ $model ?? 'Equipment catalog' }}</h1>
            <div class="flex flex-wrap items-center gap-3">
                @if (auth()->user()->isBorrower())
                    <a href="{{ route('wishlist.index') }}" class="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-blue-700 underline underline-offset-4">My wishlist</a>
                @endif
                @can('create', \App\Models\Equipment::class)
                    <a href="{{ route('equipment.create') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Add equipment</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @if ($model !== null)
        <a href="{{ route('equipment.index') }}" class="mb-3 inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline underline-offset-4">Back to equipment catalog</a>
    @endif

    @php
        $searchId = $model === null ? 'global-search' : 'model-search';
        $activeFilters = count(\Illuminate\Support\Arr::except($filters, ['q']));
    @endphp
    <form method="GET" role="search" aria-label="Equipment catalog" data-instant-filters class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        @if ($model !== null)
            <input type="hidden" name="model" value="{{ $model }}">
        @endif
        <div class="flex items-end gap-2">
            <div class="min-w-0 flex-1">
                <label for="{{ $searchId }}" class="mb-1 block text-sm font-semibold">{{ $model === null ? 'Search equipment' : 'Search this model' }}</label>
                <input id="{{ $searchId }}" type="search" name="q" data-filter-search
                       @if ($model === null) data-global-search aria-keyshortcuts="/" @endif
                       value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Model, serial, or QR"
                       class="min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
            </div>
            <button type="submit" class="min-h-11 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">{{ $model === null ? 'Search' : 'Search model' }}</button>
        </div>

        <details data-filter-disclosure class="group mt-3 rounded-md border border-slate-200"
                 @if ($activeFilters || $sort !== 'name' || $direction !== 'asc') open @endif>
            <summary class="min-h-11 cursor-pointer rounded-md bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-100 group-open:rounded-b-none">
                Filters and sorting
                @if ($activeFilters)
                    <span class="ml-2 font-medium tabular-nums text-slate-600">({{ $activeFilters }} active)</span>
                @endif
            </summary>
            <div class="grid gap-3 border-t border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block min-w-0 text-sm font-medium">
                    Department
                    <select onchange="this.form.requestSubmit()" name="department_id" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-sm font-medium">
                    Lab
                    <select onchange="this.form.requestSubmit()" name="lab_id" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        <option value="">All labs</option>
                        @foreach ($labs as $l)<option value="{{ $l->id }}" @selected(($filters['lab_id'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-sm font-medium">
                    Equipment status
                    <select onchange="this.form.requestSubmit()" name="status" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        <option value="">Any status</option>
                        @foreach (\App\Enums\EquipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-sm font-medium">
                    Equipment tag
                    <select onchange="this.form.requestSubmit()" name="tag" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        <option value="">Any tag</option>
                        @foreach ($tags as $t)<option value="{{ $t->id }}" @selected(($filters['tag'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-sm font-medium">
                    Sort by
                    <select name="sort" onchange="this.form.requestSubmit()" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        @foreach (['name' => 'Name', 'serial_number' => 'Serial number', 'status' => 'Status', 'created_at' => 'Date added'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-sm font-medium">
                    Order
                    <select name="direction" onchange="this.form.requestSubmit()" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        <option value="asc" @selected($direction === 'asc')>Ascending</option>
                        <option value="desc" @selected($direction === 'desc')>Descending</option>
                    </select>
                </label>
                <noscript>
                    <button type="submit" class="min-h-11 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Apply filters</button>
                </noscript>
            </div>
        </details>
        @if (count($filters))
            <a href="{{ route('equipment.index', $model === null ? ['clear' => 1] : ['model' => $model]) }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline underline-offset-4">Clear filter</a>
        @endif
    </form>

    <x-list-summary :paginator="$equipment" />
    @if ($equipment->isEmpty())
        <x-card class="text-center text-sm leading-relaxed text-slate-600">
            <p class="mx-auto max-w-prose text-pretty">{{ count($filters) || $model !== null ? 'No equipment matches your filters. Clear the filters to see all available items.' : 'No equipment has been added yet. Please check back later.' }}</p>
        </x-card>
    @elseif ($showUnits)
        @include('equipment._units')
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($equipment as $group)
                @php
                    $item = $group['unit'];
                    $url = $item
                        ? route('equipment.show', $item)
                        : route('equipment.index', ['model' => $group['name']] + $modelFilters);
                @endphp
                <article class="flex min-w-0 flex-col rounded-lg border border-slate-200 bg-white shadow-sm">
                    @if ($group['photo_url'])
                        <div class="aspect-video max-h-40 overflow-hidden rounded-t-lg bg-slate-50">
                            <img src="{{ $group['photo_url'] }}" alt="" loading="lazy" class="h-full w-full object-contain">
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col p-4">
                        <h2 class="font-semibold leading-snug">
                            <a href="{{ $url }}" class="inline-flex min-h-11 max-w-full items-center py-1 text-blue-800 underline decoration-blue-300 underline-offset-4 hover:decoration-current [overflow-wrap:anywhere]">{{ $item?->name ?? $group['name'] }}</a>
                        </h2>
                        @if ($item)
                            <div class="mb-2"><x-availability-glance :equipment="$item" /></div>
                            @if (auth()->user()->isBorrower())
                                <div class="mt-auto pt-2">
                                    @include('equipment._wishlist-action', ['item' => $item, 'wishlisted' => in_array($item->id, $wishlistIds)])
                                </div>
                            @endif
                        @else
                            <div class="mt-auto grid grid-cols-2 gap-2 border-t border-slate-200 pt-3 text-sm tabular-nums">
                                <p class="text-slate-600">Quantity: {{ $group['quantity'] }}</p>
                                <p class="font-semibold {{ $group['available'] > 0 ? 'text-green-700' : 'text-slate-600' }}">Available: {{ $group['available'] }}</p>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="mt-5">{{ $equipment->links() }}</div>
</x-app-layout>
