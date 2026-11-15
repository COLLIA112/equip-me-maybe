<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="page-title">{{ $equipment->name }}</h1>
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()->isBorrower())
                    <form method="POST" action="{{ route($wishlisted ? 'wishlist.destroy' : 'wishlist.store', $equipment) }}">
                        @csrf
                        @if ($wishlisted) @method('DELETE') @endif
                        <button class="rounded-md border border-blue-300 px-3 py-2 text-sm font-semibold text-blue-700">{{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}</button>
                    </form>
                @endif
                @can('reserve_equipment')
                    @if ($equipment->status->blocksReservation())
                        <span class="rounded-md bg-slate-200 px-3 py-2 text-sm text-slate-500">Under repair, cannot reserve</span>
                    @else
                        <a href="{{ route('reservations.create', $equipment) }}" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Reserve</a>
                    @endif
                @endcan
                @can('update', $equipment)
                    <a href="{{ route('equipment.edit', $equipment) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Edit</a>
                    @can('create', [\App\Models\Document::class, $equipment])
                        <a href="{{ route('documents.index', $equipment) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Documents</a>
                    @endcan
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            @if ($equipment->photo_url)
                <img src="{{ $equipment->photo_url }}" alt="{{ $equipment->name }}" class="mb-4 max-h-72 w-full rounded-md object-cover">
            @endif
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Available now</dt><dd><x-availability-glance :equipment="$equipment" /></dd></div>
                <div><dt class="text-slate-500">Status</dt><dd><x-badge :status="$equipment->status" /></dd></div>
                <div><dt class="text-slate-500">Serial number</dt><dd>{{ $equipment->serial_number }}</dd></div>
                <div><dt class="text-slate-500">Lab</dt><dd>{{ $equipment->lab->name }}</dd></div>
                <div><dt class="text-slate-500">Department</dt><dd>{{ $equipment->lab->department->name }}</dd></div>
                <div class="col-span-2">
                    <dt class="text-slate-500">Equipment QR code</dt>
                    <dd>
                        <x-equipment-qr :code="$equipment->qr_code" />
                        @can('update', $equipment)
                            <a class="mt-2 inline-block text-sm font-semibold text-blue-700 underline" href="{{ route('equipment.qr-label', $equipment) }}">Print QR label</a>
                        @endcan
                    </dd>
                </div>
                <div><dt class="text-slate-500">Tags</dt>
                    <dd class="flex flex-wrap gap-1">@forelse ($equipment->tags as $tag)<span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $tag->name }}</span>@empty<span class="text-slate-600">None</span>@endforelse</dd></div>
            </dl>
        </x-card>

        <x-card>
            <h2 class="mb-2 font-medium">Booked windows</h2>
            @forelse ($booked as $b)
                <p class="border-t first:border-t-0 py-1.5 text-sm">{{ $b->starts_at->format('M j, Y g:i A') }} to {{ $b->ends_at->format('M j, Y g:i A') }}</p>
            @empty
                <x-empty-state>No upcoming bookings.</x-empty-state>
            @endforelse
        </x-card>

        <x-card class="lg:col-span-3">
            <h2 class="mb-3 font-medium">Documents</h2>
            @if (auth()->user()->isBorrower())
                @php
                    $required = $equipment->documents->where('is_required', true);
                    $missingDownloads = $required->filter(fn ($document) => ! $progress->get($document->id)?->pivot?->downloaded_at);
                    $readCount = $required->filter(fn ($document) => $progress->get($document->id)?->pivot?->acknowledged_at)->count();
                @endphp
                @if ($required->isNotEmpty())
                    <p class="mb-2 text-sm">{{ $readCount }} of {{ $required->count() }} required documents acknowledged</p>
                    <progress class="mb-3 h-3 w-full accent-blue-700" value="{{ $readCount }}" max="{{ $required->count() }}" aria-label="Required document acknowledgement progress">{{ $readCount }} of {{ $required->count() }}</progress>
                    <form method="POST" action="{{ route('equipment.documents.acknowledge-all', $equipment) }}" class="mb-4">
                        @csrf
                        <button @disabled($missingDownloads->isNotEmpty() || $readCount === $required->count()) aria-describedby="download-required-reason" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">Mark all as read</button>
                        <p id="download-required-reason" class="mt-2 text-sm text-slate-600">{{ $missingDownloads->isNotEmpty() ? 'Download every required PDF first: '.$missingDownloads->pluck('title')->implode(', ').'.' : ($readCount === $required->count() ? 'All required documents are acknowledged.' : 'All required PDFs have been downloaded. Confirm you have read them before marking all as read.') }}</p>
                    </form>
                @endif
            @endif
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
                        <a href="{{ route('documents.download', $doc) }}" class="text-blue-600 hover:underline">Download</a>
                    </div>
                </div>
            @empty
                <x-empty-state>No documents uploaded for this item.</x-empty-state>
            @endforelse
        </x-card>
    </div>
    <x-equipment-week-calendar :equipment="$equipment" :week="$week" :bookings="$weekBookings" />
</x-app-layout>
