<x-app-layout>
    <x-slot name="header"><h1 class="page-title">{{ $department->name }} department</h1></x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-card><p class="text-sm text-slate-500">Labs</p><p class="text-3xl font-semibold">{{ $department->labs->count() }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Equipment items</p><p class="text-3xl font-semibold">{{ $department->equipment_count }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Under repair</p><p class="text-3xl font-semibold text-red-600">{{ $underRepair->count() }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Total reservations</p><p class="text-3xl font-semibold">{{ $reservationTotal }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Recorded breakdowns</p><p class="text-3xl font-semibold">{{ $breakdownTotal }}</p></x-card>
    </div>

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
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Lab</th><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Reservations</th><th class="px-4 py-3">Under repair</th></tr></thead>
            <tbody>
            @forelse ($department->labs as $lab)
                <tr class="border-t"><td class="px-4 py-3 font-medium">{{ $lab->name }}</td><td class="px-4 py-3">{{ $lab->equipment_count }}</td><td class="px-4 py-3">{{ $lab->reservations_count }}</td><td class="px-4 py-3">{{ $lab->under_repair_count }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-4"><x-empty-state>No labs in this department yet.</x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-medium">Most reserved equipment</h2>
            @forelse ($mostReserved as $e)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div><a href="{{ route('department.breakdowns', $e) }}" class="font-medium hover:underline">{{ $e->name }}</a><p class="text-slate-500">{{ $e->lab->name }}</p></div>
                    <p class="text-slate-600">{{ $e->reservations_count }} bookings · {{ $e->maintenance_logs_count }} breakdowns</p>
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
                    <a href="{{ route('department.breakdowns', $e) }}" class="text-blue-600 hover:underline">History</a>
                </div>
            @empty
                <x-empty-state>Nothing is under repair.</x-empty-state>
            @endforelse
        </x-card>
    </div>
    <x-card class="mt-6">
        <h2 class="mb-3 text-lg font-bold">Recent maintenance</h2>
        @forelse ($recentMaintenance as $log)
            <div class="flex flex-wrap items-start justify-between gap-2 border-t border-slate-200 py-3 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('department.breakdowns', $log->equipment) }}" class="break-words font-semibold text-blue-700 hover:underline">{{ $log->equipment->name }}</a>
                    <p class="break-words text-slate-700">{{ $log->issue }}</p>
                </div>
                <x-badge :status="$log->resolved_at ? 'completed' : 'under_repair'" />
            </div>
        @empty
            <x-empty-state>No maintenance activity yet.</x-empty-state>
        @endforelse
    </x-card>
</x-app-layout>
