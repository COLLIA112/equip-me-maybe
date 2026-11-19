<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Equipment tags</h1></x-slot>
    <x-card>
        <p class="text-sm text-slate-600">Tags are shared across departments. Used tags cannot be deleted.</p>
        <form method="POST" action="{{ route('tags.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div><label for="tag-name" class="block text-sm font-medium">New tag name</label><input id="tag-name" name="name" value="{{ old('name') }}" maxlength="100" required class="mt-1 rounded-md border-slate-300"></div>
            <x-primary-button>Add tag</x-primary-button>
        </form>
    </x-card>
    <div class="mt-5 space-y-3">
        @forelse ($tags as $tag)
            <x-card>
                <form method="POST" action="{{ route('tags.update', $tag) }}" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PATCH')
                    <div><label for="tag-name-{{ $tag->id }}" class="block text-sm font-medium">Tag name</label><input id="tag-name-{{ $tag->id }}" name="name" value="{{ $tag->name }}" maxlength="100" required class="mt-1 rounded-md border-slate-300"></div>
                    <x-primary-button>Save tag</x-primary-button>
                </form>
                <p class="mt-3 text-sm text-slate-600">Assigned to {{ $tag->equipment_count }} equipment items.</p>
                @if ($tag->equipment_count)
                    <p class="mt-2 text-sm text-slate-600">Deletion unavailable: this tag is assigned to equipment.</p>
                @else
                    <form method="POST" action="{{ route('tags.destroy', $tag) }}" data-confirm="Delete this unused tag?" class="mt-3">@csrf @method('DELETE')<x-danger-button>Delete unused tag</x-danger-button></form>
                @endif
            </x-card>
        @empty
            <x-empty-state>No equipment tags yet. Add the first tag above.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-5">{{ $tags->links() }}</div>
</x-app-layout>
