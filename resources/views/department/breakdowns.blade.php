<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Breakdown history: {{ $equipment->name }}</h1></x-slot>
    <x-card class="overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Reported</th><th class="px-4 py-3">Issue</th><th class="px-4 py-3">Reported by</th><th class="px-4 py-3">Borrower of record</th><th class="px-4 py-3">Resolved</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $log->created_at->format('M j, Y') }}</td>
                    <td class="px-4 py-3">{{ $log->issue }}</td>
                    <td class="px-4 py-3">{{ $log->reporter->name }}</td>
                    <td class="px-4 py-3">{{ $log->reservation?->user?->name ?? 'None' }}</td>
                    <td class="px-4 py-3">{{ $log->resolved_at?->format('M j, Y') ?? 'Open' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No breakdowns recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
