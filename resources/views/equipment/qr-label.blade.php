<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Print equipment label</h1></x-slot>

    <div class="print:hidden mb-5 flex flex-wrap gap-3" x-data>
        <x-primary-button type="button" x-on:click="window.print()">Print label</x-primary-button>
        <a class="self-center text-sm font-semibold text-blue-700 underline" href="{{ route('equipment.show', $equipment) }}">Back to equipment</a>
    </div>

    <article class="w-fit max-w-full rounded-lg border border-slate-300 bg-white p-5 text-slate-950 print:break-inside-avoid">
        <h2 class="max-w-xs break-words text-lg font-semibold">{{ $equipment->name }}</h2>
        <p class="break-words text-sm">Serial {{ $equipment->serial_number }}</p>
        <x-equipment-qr :code="$equipment->qr_code" :size="176" />
    </article>
</x-app-layout>
