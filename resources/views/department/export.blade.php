<x-app-layout>
    <x-slot name="header"><h1 class="page-title">{{ $department->name }} {{ $type === 'usage' ? 'usage report' : 'breakdown report' }}</h1></x-slot>
    <div class="mb-5 flex flex-wrap items-center gap-3 print:hidden"><button type="button" onclick="window.print()" class="rounded bg-blue-700 px-4 py-2 font-semibold text-white">Print report</button><a class="text-blue-700 underline" href="{{ route('department.index', ['period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}">Back to department</a></div>
    <h2 class="text-xl font-bold">{{ $department->name }} · {{ ucfirst($type) }}</h2>
    <p class="my-3 text-sm">{{ $range['from'] }} through {{ $range['to'] }}</p>
    <div class="responsive-table print:overflow-visible"><table class="min-w-full text-sm">
        @if($type === 'usage')
            <caption class="mb-2 text-left">Usage by equipment. Utilization uses 24-hour calendar capacity and unions overlapping approved, checked-out and returned booking hours.</caption>
            <thead><tr>@foreach(['Lab', 'Equipment', 'Serial', 'Requests', 'Breakdowns', 'Booked hours', 'Capacity hours', 'Utilization'] as $heading)<th scope="col" class="border p-2 text-left">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($usageRows as $row)<tr><td class="border p-2">{{ $row['lab'] }}</td><td class="border p-2">{{ $row['name'] }}</td><td class="border p-2">{{ $row['serial_number'] }}</td><td class="border p-2">{{ $row['reservations'] }}</td><td class="border p-2">{{ $row['breakdowns'] }}</td><td class="border p-2">{{ number_format($row['booked_hours'], 1) }}</td><td class="border p-2">{{ number_format($row['capacity_hours'], 1) }}</td><td class="border p-2">{{ number_format($row['utilization'], 1) }}%</td></tr>@empty<tr><td colspan="8" class="border p-3">No equipment in this department.</td></tr>@endforelse
        @else
            <caption class="mb-2 text-left">Breakdowns reported within the selected period. Total recorded repair cost: {{ number_format($costTotal, 2) }}</caption>
            <thead><tr>@foreach(['Lab', 'Equipment', 'Reported', 'Issue', 'Reporter', 'Borrower of record', 'Repair cost', 'Resolved'] as $heading)<th scope="col" class="border p-2 text-left">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($breakdownRows as $log)<tr><td class="border p-2">{{ $log->equipment->lab->name }}</td><td class="border p-2">{{ $log->equipment->name }}</td><td class="border p-2">{{ $log->created_at->format('M j, Y') }}</td><td class="border p-2">{{ $log->issue }}</td><td class="border p-2">{{ $log->reporter->name }}</td><td class="border p-2">{{ $log->reservation?->user?->name ?? 'None' }}</td><td class="border p-2">{{ $log->repair_cost !== null ? number_format((float) $log->repair_cost, 2) : 'Not recorded' }}</td><td class="border p-2">{{ $log->resolved_at?->format('M j, Y') ?? 'Open' }}</td></tr>@empty<tr><td colspan="8" class="border p-3">No breakdowns in this period.</td></tr>@endforelse
        @endif
        </tbody>
    </table></div>
</x-app-layout>
