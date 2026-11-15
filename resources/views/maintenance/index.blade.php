<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Maintenance log</h1></x-slot>

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
                <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Report and mark under repair</button>
            </form>
        </x-card>
    @else
        <x-empty-state class="mb-6">Add equipment in your scope before reporting a breakdown.</x-empty-state>
    @endif

    <x-card role="region" aria-label="Maintenance table" tabindex="0" class="responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Issue</th><th class="px-4 py-3">Reported by</th><th class="px-4 py-3">Borrower of record</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="border-t align-top">
                    <td class="px-4 py-3 font-medium">{{ $log->equipment->name }}<p class="text-xs font-normal text-slate-500">{{ $log->equipment->lab->name }}</p></td>
                    <td class="px-4 py-3">{{ $log->issue }}</td>
                    <td class="px-4 py-3">{{ $log->reporter->name }}</td>
                    <td class="px-4 py-3">{{ $log->reservation?->user?->name ?? 'None' }}</td>
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
                <tr><td colspan="6" class="p-4"><x-empty-state>No maintenance entries.</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
