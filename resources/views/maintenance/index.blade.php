<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Maintenance log</h1></x-slot>

    <form method="GET" data-instant-filters class="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <label for="maintenance-status" class="block text-sm font-medium">Maintenance status</label>
            <select id="maintenance-status" name="status" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
                <option value="">All entries</option>
                <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Resolved</option>
            </select>
        </div>
        @if(isset($filters['sort']))<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
        @if(isset($filters['direction']))<input type="hidden" name="direction" value="{{ $filters['direction'] }}">@endif
        @if($activeFilters)<a href="{{ route('maintenance.index', ['clear' => 1]) }}" class="text-sm font-semibold text-blue-700 underline">Clear filter</a>@endif
        <noscript><button class="rounded bg-blue-700 px-3 py-2 text-sm text-white">Update results</button></noscript>
    </form>
    <x-list-summary :paginator="$logs" />

    @if ($equipment->isNotEmpty())
        <x-card class="mb-6">
            <h2 class="mb-3 font-medium">Report a breakdown</h2>
            <form method="POST" action="{{ route('maintenance.store') }}" class="grid gap-3 sm:grid-cols-3">
                @csrf
                <select aria-label="Equipment to report" name="equipment_id" required class="rounded-md border-slate-300 text-sm">
                    <option value="">Choose equipment</option>
                    @foreach ($equipment as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                </select>
                <input aria-label="Breakdown description" name="issue" placeholder="What is wrong?" required class="rounded-md border-slate-300 text-sm sm:col-span-1">
                <div><label for="repair-cost" class="block text-sm font-medium">Repair cost (optional)</label><input id="repair-cost" name="repair_cost" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('repair_cost') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Report and mark under repair</button>
            </form>
        </x-card>
    @else
        <x-empty-state class="mb-6">Add equipment in your scope before reporting a breakdown.</x-empty-state>
    @endif

    <x-card role="region" aria-label="Maintenance table" tabindex="0" class="responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Equipment</th><th class="px-4 py-3"><x-sort-link field="issue" label="Issue" /></th><th class="px-4 py-3"><x-sort-link field="created_at" label="Reported" /></th><th class="px-4 py-3">Reported by</th><th class="px-4 py-3">Borrower of record</th><th class="px-4 py-3">Repair cost</th><th class="px-4 py-3"><x-sort-link field="resolved_at" label="Status" /></th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="border-t align-top">
                    <td class="px-4 py-3 font-medium">{{ $log->equipment->name }}<p class="text-xs font-normal text-slate-500">{{ $log->equipment->lab->name }}</p></td>
                    <td class="px-4 py-3">{{ $log->issue }}</td>
                    <td class="px-4 py-3">{{ $log->created_at->format('M j, Y') }}</td>
                    <td class="px-4 py-3">{{ $log->reporter->name }}</td>
                    <td class="px-4 py-3">{{ $log->reservation?->user?->name ?? 'None' }}</td>
                    <td class="px-4 py-3">{{ $log->repair_cost !== null ? number_format((float) $log->repair_cost, 2) : 'Not recorded' }}</td>
                    <td class="px-4 py-3">
                        @if ($log->resolved_at)<span class="text-green-700">Resolved {{ $log->resolved_at->format('M j') }}</span>@else<span class="text-red-700">Open</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @unless ($log->resolved_at)
                            <form method="POST" action="{{ route('maintenance.resolve', $log) }}" data-confirm="Mark as resolved?">
                                @csrf @method('PATCH')<button class="text-blue-600 hover:underline">Resolve</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-4"><x-empty-state>{{ $activeFilters ? 'No maintenance entries match these filters.' : 'No maintenance entries. Report a breakdown to start the history.' }}</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
