<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">Equipment catalog</h1>
            @can('create', \App\Models\Equipment::class)
                <a href="{{ route('equipment.create') }}" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add equipment</a>
            @endcan
        </div>
    </x-slot>

    <form method="GET" data-instant-filters class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-6">
        <input aria-label="Search by name or serial number" name="q" data-filter-search value="{{ $filters['q'] ?? '' }}" placeholder="Search name or serial" class="rounded-md border-slate-300 text-sm xl:col-span-2">
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
        @if (count($filters))
            <a href="{{ route('equipment.index') }}" class="text-sm font-semibold text-blue-700 hover:underline sm:col-span-2 xl:col-span-6">Clear filter</a>
        @endif
    </form>

    <x-list-summary :paginator="$equipment" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($equipment as $item)
            <a href="{{ route('equipment.show', $item) }}" class="block overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm hover:shadow">
                @if ($item->photo_url)
                    <img src="{{ $item->photo_url }}" alt="{{ $item->name }}" class="h-36 w-full object-cover">
                @else
                    <div class="flex h-36 items-center justify-center bg-slate-100 text-sm text-slate-600">No photo</div>
                @endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium">{{ $item->name }}</p>
                        <x-badge :status="$item->status" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $item->lab->name }} · {{ $item->lab->department->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($item->tags as $tag)<span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $tag->name }}</span>@endforeach
                    </div>
                </div>
            </a>
        @empty
            <x-card class="sm:col-span-2 xl:col-span-3 text-center text-sm text-slate-500">{{ count($filters) ? 'No equipment matches your filters. Clear the filters to see all available items.' : 'No equipment has been added yet. Please check back later.' }}</x-card>
        @endforelse
    </div>

    <div class="mt-5">{{ $equipment->links() }}</div>
</x-app-layout>
