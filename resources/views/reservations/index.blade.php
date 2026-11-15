<x-app-layout>
    <x-slot name="header"><h1 class="page-title">My reservations</h1></x-slot>
    <form method="GET" data-instant-filters class="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div><label for="reservation-status" class="block text-sm font-medium">Status</label>
            <select id="reservation-status" name="status" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
                <option value="">All statuses</option>
                @foreach (\App\Enums\ReservationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
        </div>
        @foreach (['sort', 'direction', 'overdue'] as $key)
            @if (isset($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
        @endforeach
        @if ($activeFilters)<a href="{{ route('reservations.index') }}" class="rounded-md px-3 py-2 text-sm font-semibold text-blue-700 underline">Clear filter</a>@endif
    </form>
    <p class="mb-3 text-sm text-slate-600">Showing {{ $reservations->firstItem() ?? 0 }}–{{ $reservations->lastItem() ?? 0 }} of {{ $reservations->total() }}</p>
    <x-card role="region" aria-label="Reservations table" tabindex="0" class="responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    @foreach (['equipment' => 'Equipment', 'starts_at' => 'Window', 'status' => 'Status'] as $key => $label)
                        <th class="px-4 py-3" @if (($filters['sort'] ?? 'starts_at') === $key) aria-sort="{{ ($filters['direction'] ?? 'desc') === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            <a class="underline" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => ($filters['sort'] ?? 'starts_at') === $key && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc', 'page' => null]) }}">{{ $label }}</a>
                        </th>
                    @endforeach
                    <th class="px-4 py-3">Lab</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($reservations as $r)
                <tr class="border-t">
                    <td class="px-4 py-3"><a class="font-medium hover:underline" href="{{ route('reservations.show', $r) }}">{{ $r->equipment->name }}</a></td>
                    <td class="px-4 py-3">{{ $r->starts_at->format('M j, Y g:i A') }} to {{ $r->ends_at->format('M j, Y g:i A') }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" />
                        @if ($r->isOverdue())<span class="ml-1 rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Overdue</span>@endif
                        @if ($r->rejection_reason)<p class="mt-2 max-w-sm text-sm text-slate-700">{{ $r->rejection_reason }}</p>@endif
                    </td>
                    <td class="px-4 py-3">{{ $r->equipment->lab->name }}</td>
                    <td class="px-4 py-3 text-right">
                        @can('cancel', $r)
                            <form method="POST" action="{{ route('reservations.cancel', $r) }}" data-confirm="Cancel this reservation?">
                                @csrf @method('PATCH')
                                <button class="text-red-600 hover:underline">Cancel</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4"><x-empty-state>{{ $activeFilters ? 'No reservations match your filters.' : 'You have no reservations yet.' }}</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-app-layout>
