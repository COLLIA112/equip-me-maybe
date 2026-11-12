<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">My reservations</h1></x-slot>
    <x-card class="overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Lab</th><th class="px-4 py-3">Window</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody>
            @forelse ($reservations as $r)
                <tr class="border-t">
                    <td class="px-4 py-3"><a class="font-medium hover:underline" href="{{ route('equipment.show', $r->equipment) }}">{{ $r->equipment->name }}</a></td>
                    <td class="px-4 py-3">{{ $r->equipment->lab->name }}</td>
                    <td class="px-4 py-3">{{ $r->starts_at->format('M j, g:i A') }} to {{ $r->ends_at->format('g:i A') }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3 text-right">
                        @can('cancel', $r)
                            <form method="POST" action="{{ route('reservations.cancel', $r) }}" onsubmit="return confirm('Cancel this reservation?')">
                                @csrf @method('PATCH')
                                <button class="text-red-600 hover:underline">Cancel</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">You have no reservations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-app-layout>
