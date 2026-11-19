<x-app-layout>
    <x-slot name="header"><h1 class="page-title">{{ $department->name }} department</h1></x-slot>

    @include('department._range-filter')
    <p class="mb-4 text-sm text-slate-700">Reporting {{ $range['from'] }} through {{ $range['to'] }}. Reservation requests count all statuses with a window overlapping these dates; breakdowns use their report date. Repair counts are a current snapshot.</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-card><p class="text-sm text-slate-500">Labs</p><p class="text-3xl font-semibold">{{ $department->labs->count() }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Equipment items</p><p class="text-3xl font-semibold">{{ $department->equipment_count }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Under repair</p><p class="text-3xl font-semibold text-red-600">{{ $underRepair->count() }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Total reservations</p><p class="text-3xl font-semibold">{{ $reservationTotal }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Recorded breakdowns</p><p class="text-3xl font-semibold">{{ $breakdownTotal }}</p></x-card>
    </div>

    <div class="mt-4 flex flex-wrap gap-3 text-sm font-semibold text-blue-700 print:hidden">
        @foreach(['usage' => 'Usage', 'breakdowns' => 'Breakdowns'] as $type => $label)
            <a class="underline" href="{{ route('department.export', ['type' => $type, 'format' => 'csv', 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}">{{ $label }} CSV</a>
            <a class="underline" href="{{ route('department.export', ['type' => $type, 'format' => 'print', 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}">Print {{ strtolower($label) }}</a>
        @endforeach
    </div>
    <p class="mt-3 text-sm font-semibold">Recorded repair costs in period: {{ number_format($costTotal, 2) }}</p>

    <x-card class="mt-5">
        <h2 class="mb-3 text-lg font-bold">Quick actions</h2>
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-blue-700">
            <a class="hover:underline" href="{{ route('equipment.create') }}">Add equipment</a>
            <a class="hover:underline" href="{{ route('maintenance.index') }}">Open maintenance</a>
            <a class="hover:underline" href="{{ route('department.users') }}">Manage users and roles</a>
        </div>
    </x-card>

    <x-card role="region" aria-label="Department table" tabindex="0" class="mt-6 responsive-table p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Lab</th><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Reservations</th><th class="px-4 py-3">Under repair now</th><th class="px-4 py-3">Utilization</th></tr></thead>
            <tbody>
            @forelse ($department->labs as $lab)
                <tr class="border-t"><td class="px-4 py-3 font-medium">{{ $lab->name }}</td><td class="px-4 py-3">{{ $lab->equipment_count }}</td><td class="px-4 py-3">{{ $lab->reservations_count }}</td><td class="px-4 py-3">{{ $lab->under_repair_count }}</td><td class="px-4 py-3">{{ number_format($labUsage[$lab->id]['utilization'], 1) }}%<p class="text-xs text-slate-600">{{ number_format($labUsage[$lab->id]['booked_hours'], 1) }} booked hours / {{ number_format($labUsage[$lab->id]['capacity_hours'], 1) }} hours</p></td></tr>
            @empty
                <tr><td colspan="5" class="p-4"><x-empty-state>No labs in this department yet.</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>

    <p class="mt-2 text-xs text-slate-600">Utilization uses 24-hour capacity: equipment count × 24 hours × selected days. Only approved, checked-out and returned booking windows contribute; overlapping hours per item count once. This measures calendar capacity, not class or opening hours.</p>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-medium">Most reserved equipment</h2>
            @forelse ($mostReserved as $e)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div><a href="{{ route('department.breakdowns', ['equipment' => $e, 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}" class="font-medium hover:underline">{{ $e->name }}</a><p class="text-slate-500">{{ $e->lab->name }}</p></div>
                    <p class="text-slate-600">{{ $e->reservations_count }} bookings · {{ $e->maintenance_logs_count }} breakdowns</p>
                    <div class="h-2 w-24 rounded bg-slate-100" aria-hidden="true"><div class="h-2 rounded bg-blue-700" style="width: {{ $mostReserved->max('reservations_count') > 0 ? round($e->reservations_count / $mostReserved->max('reservations_count') * 100) : 0 }}%"></div></div>
                </div>
            @empty
                <x-empty-state>No equipment in this department yet.</x-empty-state>
            @endforelse
        </x-card>
        <x-card>
            <h2 class="mb-3 font-medium">Currently under repair</h2>
            @forelse ($underRepair as $e)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div><p class="font-medium">{{ $e->name }}</p><p class="text-slate-500">{{ $e->lab->name }}</p></div>
                    <a href="{{ route('department.breakdowns', ['equipment' => $e, 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}" class="text-blue-600 hover:underline">History</a>
                </div>
            @empty
                <x-empty-state>Nothing is under repair.</x-empty-state>
            @endforelse
        </x-card>
    </div>
    <x-card class="mt-6">
        <h2 class="mb-3 text-lg font-bold">Breakdown trend</h2>
        <p class="mb-3 text-sm text-slate-600">New breakdown reports during the selected period. Each bar represents one {{ $trend->count() > 0 && strlen($trend->first()['label']) === 7 ? 'month' : 'day' }}.</p>
        @php $peak = max(1, $trend->max('count') ?? 0); @endphp
        <div class="flex h-40 items-end gap-1 overflow-x-auto border-b border-slate-300" role="img" aria-label="Breakdown counts by date; exact values are in the table below">
            @foreach($trend as $point)
                <div class="min-w-3 flex-1 bg-red-700" style="height: {{ round($point['count'] / $peak * 140) }}px" title="{{ $point['label'] }}: {{ $point['count'] }} breakdowns"></div>
            @endforeach
        </div>
        <details class="mt-3"><summary class="cursor-pointer text-sm font-semibold text-blue-700">Exact breakdown counts by date</summary>
            <div class="responsive-table mt-2"><table class="min-w-full text-sm"><caption class="sr-only">Breakdown trend data</caption><thead><tr><th scope="col" class="p-2 text-left">Date</th><th scope="col" class="p-2 text-left">Breakdowns</th></tr></thead><tbody>
            @foreach($trend as $point)<tr><td class="p-2">{{ $point['label'] }}</td><td class="p-2">{{ $point['count'] }}</td></tr>@endforeach
            </tbody></table></div>
        </details>
    </x-card>
    <x-card class="mt-6">
        <h2 class="mb-3 text-lg font-bold">Recent maintenance</h2>
        @forelse ($recentMaintenance as $log)
            <div class="flex flex-wrap items-start justify-between gap-2 border-t border-slate-200 py-3 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('department.breakdowns', ['equipment' => $log->equipment, 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']]) }}" class="break-words font-semibold text-blue-700 hover:underline">{{ $log->equipment->name }}</a>
                    <p class="break-words text-slate-700">{{ $log->issue }}</p>
                    @if($log->repair_cost !== null)<p class="text-xs text-slate-600">Repair cost: {{ number_format((float) $log->repair_cost, 2) }}</p>@endif
                </div>
                <x-badge :status="$log->resolved_at ? 'completed' : 'under_repair'" />
            </div>
        @empty
            <x-empty-state>No maintenance activity yet.</x-empty-state>
        @endforelse
    </x-card>
</x-app-layout>
