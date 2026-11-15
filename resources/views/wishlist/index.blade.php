<x-app-layout>
    <x-slot name="header"><h1 class="page-title">My wishlist</h1></x-slot>
    <p class="mb-4 text-sm text-slate-600">Saved items do not hold a time slot. Notifications appear here in the app when availability changes.</p>
    <x-list-summary :paginator="$equipment" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($equipment as $item)
            <x-card>
                <a class="font-semibold text-blue-700 underline" href="{{ route('equipment.show', $item) }}">{{ $item->name }}</a>
                <p class="my-2 text-sm text-slate-600">{{ $item->lab->name }} · {{ $item->lab->department->name }}</p>
                <x-badge :status="$item->status" />
                <form method="POST" action="{{ route('wishlist.destroy', $item) }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button class="text-sm font-semibold text-red-700 underline">Remove from wishlist</button>
                </form>
            </x-card>
        @empty
            <x-empty-state>Save equipment from its detail page to start your wishlist.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-4">{{ $equipment->links() }}</div>
</x-app-layout>
