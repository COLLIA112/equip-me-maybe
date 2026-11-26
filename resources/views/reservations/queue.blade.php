<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Reservation queue</h1></x-slot>
    <form method="GET" data-instant-filters class="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div><label for="queue-status" class="block text-sm font-medium">Status</label>
            <select id="queue-status" name="status" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
                <option value="">Open reservations</option>
                @foreach (\App\Enums\ReservationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
        </div>
        <div><label for="queue-borrower" class="block text-sm font-medium">Borrower name or email</label>
            <input id="queue-borrower" name="q" data-filter-search value="{{ $filters['q'] ?? '' }}" maxlength="150" class="mt-1 w-full rounded-md border-slate-300 text-sm" type="search">
        </div>
        <div><label for="queue-overdue" class="block text-sm font-medium">Return date</label>
            <select id="queue-overdue" name="overdue" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
                <option value="">All return dates</option><option value="1" @selected(($filters['overdue'] ?? '') === '1')>Overdue returns</option>
            </select>
        </div>
        @foreach (['sort', 'direction'] as $key)
            @if (isset($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
        @endforeach
        @if ($activeFilters)<a href="{{ route('reservations.queue', ['clear' => 1]) }}" class="rounded-md px-3 py-2 text-sm font-semibold text-blue-700 underline">Clear filter</a>@endif
    </form>
    <div class="mb-5 flex flex-wrap gap-3">
        <a href="{{ route('reservations.counter') }}" class="font-semibold text-blue-700 underline">Open handover and return counter</a>
    </div>
    <form id="bulk-reservations" method="POST" action="{{ route('reservations.bulk') }}" data-confirm="Apply this action to the selected pending reservations?" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4">
        @csrf
        <div><label for="bulk-action" class="block text-sm font-medium">Selected pending requests</label><select name="action" id="bulk-action" class="rounded-md border-slate-300 text-sm"><option value="approve">Approve selected</option><option value="reject">Reject selected</option></select></div>
        <div><label for="bulk-reason" class="block text-sm font-medium">Rejection reason (optional)</label><input name="rejection_reason" id="bulk-reason" maxlength="1000" class="rounded-md border-slate-300 text-sm"></div>
        <button class="rounded bg-blue-700 px-4 py-2 text-sm text-white">Apply to selected (maximum 50)</button>
    </form>
    @if (session('bulk_result.errors'))<div role="status" class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"><p class="font-semibold">Some selected requests could not be changed:</p><ul class="list-disc pl-5">@foreach (session('bulk_result.errors') as $id => $messages)<li>Reservation #{{ $id }}: {{ implode(' ', $messages) }}</li>@endforeach</ul></div>@endif
    <p class="mb-3 text-sm text-slate-600">Showing {{ $reservations->firstItem() ?? 0 }}–{{ $reservations->lastItem() ?? 0 }} of {{ $reservations->total() }}</p>
    <x-card role="region" aria-label="Reservation queue table" tabindex="0" class="responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-3"><span class="sr-only">Select pending request</span></th>
                    @foreach (['equipment' => 'Equipment', 'borrower' => 'Borrower', 'starts_at' => 'Window', 'status' => 'Status'] as $key => $label)
                        <th class="px-4 py-3" @if (($filters['sort'] ?? 'starts_at') === $key) aria-sort="{{ ($filters['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            <a class="underline" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => ($filters['sort'] ?? 'starts_at') === $key && ($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc', 'page' => null]) }}">{{ $label }}</a>
                        </th>
                    @endforeach
                    <th class="px-4 py-3">Documents</th><th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($reservations as $r)
                @php $ok = $compliance[$r->id] ?? false; $missing = $missingDocuments[$r->id] ?? []; @endphp
                <tr class="border-t align-top">
                    <td class="px-3 py-3">@if ($r->status === \App\Enums\ReservationStatus::Pending)<input type="checkbox" form="bulk-reservations" name="reservation_ids[]" value="{{ $r->id }}" aria-label="Select reservation {{ $r->id }}" class="rounded border-slate-400">@endif</td>
                    <td class="px-4 py-3 font-medium"><a class="text-blue-700 hover:underline" href="{{ route('reservations.show', $r) }}">{{ $r->equipment->name }}</a>
                        @if ($r->is_class_session)<p class="text-xs text-slate-600">Faculty class session</p>@endif
                    </td>
                    <td class="px-4 py-3">{{ $r->user->name }}</td>
                    <td class="px-4 py-3">{{ $r->starts_at->format('M j, Y g:i A') }} to {{ $r->ends_at->format('M j, Y g:i A') }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" />
                        @if ($r->isOverdue())<span class="mt-2 inline-block rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Overdue</span>@endif
                    </td>
                    <td id="compliance-{{ $r->id }}" class="px-4 py-3">
                        @if ($ok)<span class="text-green-700">All acknowledged</span>@else<span class="text-red-700">Must acknowledge: {{ implode(', ', $missing) }}.</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($r->status === \App\Enums\ReservationStatus::Pending)
                                <form method="POST" action="{{ route('reservations.approve', $r) }}">@csrf @method('PATCH')
                                    <button @disabled(! $ok) aria-describedby="compliance-{{ $r->id }}" class="rounded bg-green-700 px-3 py-1 text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-40">Approve</button></form>
                                <form method="POST" action="{{ route('reservations.reject', $r) }}" data-confirm="Reject this request?" class="space-y-2">@csrf @method('PATCH')
                                    <label for="rejection-reason-{{ $r->id }}" class="block text-xs font-medium">Rejection reason (optional)</label>
                                    <input id="rejection-reason-{{ $r->id }}" name="rejection_reason" maxlength="1000" class="w-full min-w-32 rounded-md border-slate-300 text-sm" value="{{ old('rejection_reason') }}">
                                    <button class="rounded bg-red-600 px-3 py-1 text-white">Reject</button></form>
                            @elseif ($r->status === \App\Enums\ReservationStatus::Approved)
                                <form method="POST" action="{{ route('reservations.handover', $r) }}">@csrf @method('PATCH')
                                    <button @disabled(! $ok) aria-describedby="compliance-{{ $r->id }}" class="rounded bg-blue-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:opacity-40">Hand over</button></form>
                                    <form method="POST" action="{{ route('reservations.revoke', $r) }}" data-confirm="Cancel this approved booking?">
                                        @csrf @method('PATCH')
                                        <input name="cancellation_reason" required minlength="5" maxlength="1000" placeholder="Reason" class="rounded-md border-slate-300 text-sm">
                                        <button class="text-red-600 hover:underline">Cancel booking</button>
                                    </form>
                            @elseif ($r->status === \App\Enums\ReservationStatus::CheckedOut)
                                <form method="POST" action="{{ route('reservations.return', $r) }}" data-confirm="Record this item as returned?">@csrf @method('PATCH')
                                    @include('reservations._return-condition')
                                    <button class="rounded bg-slate-700 px-3 py-1 text-white">Receive return</button></form>
                            @else
                                <a class="text-blue-700 underline" href="{{ route('reservations.show', $r) }}">View details</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-4"><x-empty-state>{{ $activeFilters ? 'No reservations match your filters.' : 'No open reservations for your lab.' }}</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-app-layout>
