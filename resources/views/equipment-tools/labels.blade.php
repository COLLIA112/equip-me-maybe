<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Print equipment QR labels</h1></x-slot>
    <div class="mb-5 flex flex-wrap items-center gap-4 print:hidden" x-data>
        <x-primary-button type="button" x-on:click="window.print()">Print labels</x-primary-button>
        <a href="{{ route('equipment.tools.index') }}" class="text-sm font-semibold text-blue-700 underline">Back to equipment tools</a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 print:grid-cols-3">
        @forelse ($equipment as $item)
            <article class="break-inside-avoid rounded-lg border border-slate-300 bg-white p-4 text-slate-950 print:break-inside-avoid" aria-label="Label for {{ $item->name }}">
                <h2 class="break-words font-semibold">{{ $item->name }}</h2>
                <p class="break-words text-sm">Serial {{ $item->serial_number }}</p>
                <p class="text-sm">{{ $item->lab->name }}</p>
                <x-equipment-qr :code="$item->qr_code" :size="144" />
            </article>
        @empty
            <x-empty-state>No equipment labels to print in your managed laboratories.</x-empty-state>
        @endforelse
    </div>
</x-app-layout>
