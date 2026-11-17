<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Equipment tools</h1></x-slot>
    <x-card>
        <h2 class="text-lg font-semibold">Export and print</h2>
        <p class="mt-2 text-sm text-slate-600">Downloads include only equipment you manage. Spreadsheet formula cells are escaped in CSV exports.</p>
        <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-blue-700">
            <a class="underline" href="{{ route('equipment.tools.export', request()->only('lab_id')) }}">Export equipment CSV</a>
            <a class="underline" href="{{ route('equipment.tools.labels', request()->only('lab_id')) }}">Print all QR labels</a>
            <a class="underline" href="{{ route('equipment.tools.template') }}">Download import template</a>
        </div>
    </x-card>
    @if (auth()->user()->hasRole(\App\Enums\Role::LabManager))
        <x-card class="mt-5">
            <h2 class="text-lg font-semibold">Import equipment into your laboratory</h2>
            <p class="mt-2 text-sm text-slate-600">Upload a UTF-8 CSV up to 2 MB and 500 rows. Required columns: name, serial_number. Optional tags must match existing names separated by |. Every row is checked before any equipment is created.</p>
            <form method="POST" action="{{ route('equipment.tools.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div><label for="equipment-csv" class="block text-sm font-medium">Equipment CSV</label><input id="equipment-csv" type="file" name="file" accept=".csv,text/csv" required class="mt-1 block max-w-full text-sm"></div>
                <x-primary-button>Validate and import CSV</x-primary-button>
            </form>
        </x-card>
    @endif
    <div class="mt-5 space-y-4">
        @forelse ($equipment as $item)
            <x-card>
                <h2 class="break-words font-semibold">{{ $item->name }}</h2>
                <p class="mt-1 break-words text-sm text-slate-600">{{ $item->serial_number }} · {{ $item->lab->name }}</p>
                @if (auth()->user()->hasRole(\App\Enums\Role::LabManager))
                    <p class="mt-2 text-sm text-slate-600">Clone the item’s basic details and tags. Supply its new serial number. Photos, documents, reservations, and maintenance history are not copied.</p>
                    <form method="POST" action="{{ route('equipment.tools.clone', $item) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                        @csrf
                        <div><label for="clone-name-{{ $item->id }}" class="block text-sm font-medium">New equipment name</label><input id="clone-name-{{ $item->id }}" name="name" value="{{ $item->name }}" maxlength="150" required class="mt-1 w-full rounded-md border-slate-300"></div>
                        <div><label for="clone-serial-{{ $item->id }}" class="block text-sm font-medium">New serial number</label><input id="clone-serial-{{ $item->id }}" name="serial_number" maxlength="100" required class="mt-1 w-full rounded-md border-slate-300"></div>
                        <div class="self-end"><x-primary-button>Clone equipment</x-primary-button></div>
                    </form>
                @endif
            </x-card>
        @empty
            <x-empty-state>No equipment in your managed laboratories yet.</x-empty-state>
        @endforelse
    </div>
</x-app-layout>
