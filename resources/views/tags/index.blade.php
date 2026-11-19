@php $isHead = auth()->user()->can('view_department_reports'); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Equipment tags</h1>
            <a href="{{ route('equipment.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Back to catalog</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-3 font-medium">Add a tag</h2>
            <form method="POST" action="{{ route('tags.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="new-tag" class="block text-sm font-medium">Name</label>
                    <input id="new-tag" name="name" value="{{ old('name') }}" required maxlength="50"
                           class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="e.g. needs supervision">
                </div>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create tag</button>
            </form>
            @unless ($isHead)
                <p class="mt-4 text-xs text-slate-500">Tags are shared by every lab, so only a department head can rename or delete them.</p>
            @endunless
        </x-card>

        <x-card class="lg:col-span-2">
            <h2 class="mb-3 font-medium">All tags ({{ $tags->count() }})</h2>
            @forelse ($tags as $tag)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t first:border-t-0 py-2 text-sm">
                    @if ($isHead)
                        <form method="POST" action="{{ route('tags.update', $tag) }}" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <label for="tag-{{ $tag->id }}" class="sr-only">Tag name</label>
                            <input id="tag-{{ $tag->id }}" name="name" value="{{ $tag->name }}" required maxlength="50"
                                   class="w-48 rounded-md border-slate-300 text-sm">
                            <button class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs hover:bg-slate-50">Rename</button>
                        </form>
                    @else
                        <span class="font-medium">{{ $tag->name }}</span>
                    @endif

                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-500">{{ $tag->equipment_count }} {{ \Illuminate\Support\Str::plural('item', $tag->equipment_count) }}</span>
                        @if ($isHead)
                            <form method="POST" action="{{ route('tags.destroy', $tag) }}"
                                  onsubmit="return confirm('Delete the tag {{ e($tag->name) }}? It will be removed from {{ $tag->equipment_count }} item(s).')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No tags yet. Create the first one.</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
