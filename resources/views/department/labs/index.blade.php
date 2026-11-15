<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Laboratories</h1></x-slot>
    <x-card>
        <h2 class="text-lg font-semibold">Add laboratory</h2>
        <form method="POST" action="{{ route('department.labs.store') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
            @csrf
            <div><label for="lab-name" class="block text-sm font-medium">Name</label><input id="lab-name" name="name" value="{{ old('name') }}" maxlength="150" required class="mt-1 w-full rounded-md border-slate-300"></div>
            <div><label for="lab-location" class="block text-sm font-medium">Location</label><input id="lab-location" name="location" value="{{ old('location') }}" maxlength="255" class="mt-1 w-full rounded-md border-slate-300"></div>
            <div class="self-end"><x-primary-button>Add laboratory</x-primary-button></div>
        </form>
        <p class="mt-3 text-sm text-slate-600">Assign lab managers in <a class="font-semibold text-blue-700 underline" href="{{ route('department.users') }}">Users and roles</a>.</p>
    </x-card>
    <div class="mt-5 space-y-4">
        @forelse ($labs as $lab)
            <x-card>
                <form method="POST" action="{{ route('department.labs.update', $lab) }}" class="grid gap-3 sm:grid-cols-3">
                    @csrf @method('PATCH')
                    <div><label for="lab-name-{{ $lab->id }}" class="block text-sm font-medium">Laboratory name</label><input id="lab-name-{{ $lab->id }}" name="name" value="{{ $lab->name }}" maxlength="150" required class="mt-1 w-full rounded-md border-slate-300"></div>
                    <div><label for="lab-location-{{ $lab->id }}" class="block text-sm font-medium">Location</label><input id="lab-location-{{ $lab->id }}" name="location" value="{{ $lab->location }}" maxlength="255" class="mt-1 w-full rounded-md border-slate-300"></div>
                    <div class="self-end"><x-primary-button>Save laboratory</x-primary-button></div>
                </form>
                <p class="mt-3 text-sm text-slate-600">{{ $lab->equipment_count }} equipment items · {{ $lab->users_count }} assigned users</p>
                @if ($lab->equipment_count || $lab->users_count)
                    <p class="mt-2 text-sm text-slate-600">Deletion unavailable: this laboratory has equipment or assigned users.</p>
                @else
                    <form method="POST" action="{{ route('department.labs.destroy', $lab) }}" data-confirm="Delete this empty laboratory?" class="mt-3">@csrf @method('DELETE')<x-danger-button>Delete empty laboratory</x-danger-button></form>
                @endif
            </x-card>
        @empty
            <x-empty-state>No laboratories in your department. Add the first laboratory above.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-5">{{ $labs->links() }}</div>
</x-app-layout>
