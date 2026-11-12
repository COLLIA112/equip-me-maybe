<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">{{ $department->name }} department</h1></x-slot>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-card><p class="text-sm text-slate-500">Labs</p><p class="text-3xl font-semibold">{{ $department->labs->count() }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Equipment items</p><p class="text-3xl font-semibold">{{ $department->equipment_count }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Under repair</p><p class="text-3xl font-semibold text-red-600">{{ $underRepair->count() }}</p></x-card>
    </div>

    <x-card class="mt-6 overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Lab</th><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Reservations</th><th class="px-4 py-3">Under repair</th></tr></thead>
            <tbody>
            @foreach ($department->labs as $lab)
                <tr class="border-t"><td class="px-4 py-3 font-medium">{{ $lab->name }}</td><td class="px-4 py-3">{{ $lab->equipment_count }}</td><td class="px-4 py-3">{{ $lab->reservations_count }}</td><td class="px-4 py-3">{{ $lab->under_repair_count }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </x-card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-medium">Most reserved equipment</h2>
            @foreach ($mostReserved as $e)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div><a href="{{ route('department.breakdowns', $e) }}" class="font-medium hover:underline">{{ $e->name }}</a><p class="text-slate-500">{{ $e->lab->name }}</p></div>
                    <p class="text-slate-600">{{ $e->reservations_count }} bookings · {{ $e->maintenance_logs_count }} breakdowns</p>
                </div>
            @endforeach
        </x-card>
        <x-card>
            <h2 class="mb-3 font-medium">Currently under repair</h2>
            @forelse ($underRepair as $e)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div><p class="font-medium">{{ $e->name }}</p><p class="text-slate-500">{{ $e->lab->name }}</p></div>
                    <a href="{{ route('department.breakdowns', $e) }}" class="text-indigo-600 hover:underline">History</a>
                </div>
            @empty
                <p class="text-sm text-slate-500">Nothing is under repair.</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
