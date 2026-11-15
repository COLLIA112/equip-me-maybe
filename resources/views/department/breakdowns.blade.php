<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Breakdown history: {{ $equipment->name }}</h1></x-slot>
    @if($dateFiltered)<p class="mb-3 text-sm text-slate-700">Reported {{ $range['from'] }} through {{ $range['to'] }}. <a class="text-blue-700 underline" href="{{ route('department.breakdowns', ['equipment' => $equipment, 'clear' => 1]) }}">Show all history</a></p>@endif
    <x-list-summary :paginator="$logs" />
    <x-card role="region" aria-label="Breakdown history table" tabindex="0" class="responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Reported</th><th class="px-4 py-3">Issue</th><th class="px-4 py-3">Reported by</th><th class="px-4 py-3">Borrower of record</th><th class="px-4 py-3">Repair cost</th><th class="px-4 py-3">Resolved</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $log->created_at->format('M j, Y') }}</td>
                    <td class="px-4 py-3">{{ $log->issue }}</td>
                    <td class="px-4 py-3">{{ $log->reporter->name }}</td>
                    <td class="px-4 py-3">{{ $log->reservation?->user?->name ?? 'None' }}</td>
                    <td class="px-4 py-3">{{ $log->repair_cost !== null ? number_format((float) $log->repair_cost, 2) : 'Not recorded' }}</td>
                    <td class="px-4 py-3">{{ $log->resolved_at?->format('M j, Y') ?? 'Open' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4"><x-empty-state>No breakdowns recorded.</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
