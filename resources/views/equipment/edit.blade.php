<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Edit {{ $equipment->name }}</h1></x-slot>
    <x-card class="max-w-2xl">
        @include('equipment._form')
        <form method="POST" action="{{ route('equipment.destroy', $equipment) }}" class="mt-6 border-t pt-4"
              onsubmit="return confirm('Delete this equipment and its history?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">Delete equipment</button>
        </form>
    </x-card>
</x-app-layout>
