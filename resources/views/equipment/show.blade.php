<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ $equipment->name }}</h1>
            <div class="flex gap-2">
                @can('reserve_equipment')
                    @if ($equipment->status->blocksReservation())
                        <span class="rounded-md bg-slate-200 px-3 py-2 text-sm text-slate-500">Under repair, cannot reserve</span>
                    @else
                        <a href="{{ route('reservations.create', $equipment) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Reserve</a>
                    @endif
                @endcan
                @can('update', $equipment)
                    <a href="{{ route('equipment.edit', $equipment) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Edit</a>
                    <a href="{{ route('documents.index', $equipment) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Documents</a>
                @endcan
                @if ($canDelete)
                    @if ($deleteBlocker)
                        <span title="{{ $deleteBlocker }}" class="cursor-not-allowed rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400">Delete unavailable</span>
                    @else
                        <button type="button" x-data x-on:click.prevent="$dispatch('open-modal', 'confirm-equipment-deletion')"
                                class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50">Delete</button>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    @if ($canDelete && $deleteBlocker)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            This item cannot be deleted. {{ $deleteBlocker }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            @if ($equipment->photo_url)
                <img src="{{ $equipment->photo_url }}" alt="{{ $equipment->name }}" class="mb-4 max-h-72 w-full rounded-md object-cover">
            @endif
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Status</dt><dd><x-badge :status="$equipment->status" /></dd></div>
                <div><dt class="text-slate-500">Serial number</dt><dd>{{ $equipment->serial_number }}</dd></div>
                <div><dt class="text-slate-500">Lab</dt><dd>{{ $equipment->lab->name }}</dd></div>
                <div><dt class="text-slate-500">Department</dt><dd>{{ $equipment->lab->department->name }}</dd></div>
                <div><dt class="text-slate-500">Equipment code</dt><dd class="font-mono">{{ $equipment->qr_code }}</dd></div>
                <div><dt class="text-slate-500">Tags</dt>
                    <dd class="flex flex-wrap gap-1">@forelse ($equipment->tags as $tag)<span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $tag->name }}</span>@empty<span class="text-slate-400">None</span>@endforelse</dd></div>
            </dl>
        </x-card>

        <x-card>
            <h2 class="mb-2 font-medium">Booked windows</h2>
            @forelse ($booked as $b)
                <p class="border-t first:border-t-0 py-1.5 text-sm">{{ $b->starts_at->format('M j, g:i A') }} to {{ $b->ends_at->format('g:i A') }}</p>
            @empty
                <p class="text-sm text-slate-500">No upcoming bookings. Any slot is free.</p>
            @endforelse
        </x-card>

        <x-card class="lg:col-span-3">
            <h2 class="mb-3 font-medium">Documents</h2>
            @forelse ($equipment->documents as $doc)
                @php $pivot = $progress->get($doc->id)?->pivot; @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 border-t first:border-t-0 py-2 text-sm">
                    <div>
                        <p class="font-medium">{{ $doc->title }}
                            @if ($doc->is_required)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Required</span>@endif</p>
                        <p class="text-slate-500">{{ ucfirst(str_replace('_', ' ', $doc->type)) }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if (auth()->user()->isBorrower())
                            <span class="text-xs text-slate-500">
                                {{ $pivot?->acknowledged_at ? 'Acknowledged' : ($pivot?->downloaded_at ? 'Downloaded, not acknowledged' : 'Not downloaded') }}
                            </span>
                        @endif
                        <a href="{{ route('documents.download', $doc) }}" class="text-indigo-600 hover:underline">Download</a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No documents uploaded for this item.</p>
            @endforelse
        </x-card>
    </div>
    @if ($canDelete && ! $deleteBlocker)
        <x-modal name="confirm-equipment-deletion" focusable>
            <form method="POST" action="{{ route('equipment.destroy', $equipment) }}" class="p-6">
                @csrf @method('DELETE')
                <h2 class="text-lg font-semibold text-slate-900">Delete {{ $equipment->name }}?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    This permanently removes the item, its photo, its tags and its documents. This cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
                    <button class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete equipment</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
