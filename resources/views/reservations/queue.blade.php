<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Reservation queue</h1></x-slot>
    <x-card class="overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Equipment</th><th class="px-4 py-3">Borrower</th><th class="px-4 py-3">Window</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Documents</th><th class="px-4 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($reservations as $r)
                @php $ok = $compliance[$r->id] ?? false; @endphp
                <tr class="border-t align-top">
                    <td class="px-4 py-3 font-medium">{{ $r->equipment->name }}</td>
                    <td class="px-4 py-3">{{ $r->user->name }}</td>
                    <td class="px-4 py-3">{{ $r->starts_at->format('M j, g:i A') }} to {{ $r->ends_at->format('g:i A') }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3">
                        @if ($ok)<span class="text-green-700">All acknowledged</span>@else<span class="text-red-700">Missing acknowledgements</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if ($r->status === \App\Enums\ReservationStatus::Pending)
                                <form method="POST" action="{{ route('reservations.approve', $r) }}">@csrf @method('PATCH')
                                    <button @disabled(! $ok) class="rounded bg-green-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:opacity-40">Approve</button></form>
                                <form method="POST" action="{{ route('reservations.reject', $r) }}" onsubmit="return confirm('Reject this request?')">@csrf @method('PATCH')
                                    <button class="rounded bg-red-600 px-3 py-1 text-white">Reject</button></form>
                            @elseif ($r->status === \App\Enums\ReservationStatus::Approved)
                                <form method="POST" action="{{ route('reservations.handover', $r) }}">@csrf @method('PATCH')
                                    <button @disabled(! $ok) class="rounded bg-indigo-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:opacity-40">Hand over</button></form>
                            @elseif ($r->status === \App\Enums\ReservationStatus::CheckedOut)
                                <form method="POST" action="{{ route('reservations.return', $r) }}" onsubmit="return confirm('Record this item as returned?')">@csrf @method('PATCH')
                                    <button class="rounded bg-slate-700 px-3 py-1 text-white">Receive return</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No open reservations for your lab.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-app-layout>
