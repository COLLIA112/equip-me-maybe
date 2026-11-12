<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Equipment catalog</h1>
            @can('create', \App\Models\Equipment::class)
                <a href="{{ route('equipment.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add equipment</a>
            @endcan
        </div>
    </x-slot>

    <form method="GET" class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or serial" class="rounded-md border-slate-300 text-sm lg:col-span-2">
        <select name="department_id" class="rounded-md border-slate-300 text-sm">
            <option value="">All departments</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <select name="lab_id" class="rounded-md border-slate-300 text-sm">
            <option value="">All labs</option>
            @foreach ($labs as $l)<option value="{{ $l->id }}" @selected(($filters['lab_id'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
        </select>
        <select name="status" class="rounded-md border-slate-300 text-sm">
            <option value="">Any status</option>
            @foreach (\App\Enums\EquipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
        </select>
        <select name="tag" class="rounded-md border-slate-300 text-sm">
            <option value="">Any tag</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected(($filters['tag'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
            <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Filter</button>
            <a href="{{ route('equipment.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($equipment as $item)
            <a href="{{ route('equipment.show', $item) }}" class="block overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm hover:shadow">
                @if ($item->photo_url)
                    <img src="{{ $item->photo_url }}" alt="{{ $item->name }}" class="h-36 w-full object-cover">
                @else
                    <div class="flex h-36 items-center justify-center bg-slate-100 text-sm text-slate-400">No photo</div>
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
            <x-card class="sm:col-span-2 lg:col-span-3 text-center text-sm text-slate-500">No equipment matches those filters.</x-card>
        @endforelse
    </div>

    <div class="mt-5">{{ $equipment->links() }}</div>
</x-app-layout>
