<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Reservation #{{ $reservation->id }}</h1>
        <x-badge :status="$reservation->status" />
    </x-slot>

    @php
        $status = $reservation->status;
        $ended = in_array($status, [\App\Enums\ReservationStatus::Rejected, \App\Enums\ReservationStatus::Cancelled], true);
        $steps = [
            ['Requested', true, $reservation->created_at],
            ['Approved', $reservation->approved_by !== null || in_array($status, [\App\Enums\ReservationStatus::Approved, \App\Enums\ReservationStatus::CheckedOut, \App\Enums\ReservationStatus::Returned], true), null],
            ['Checked out', $reservation->handed_over_at !== null || $status === \App\Enums\ReservationStatus::CheckedOut, $reservation->handed_over_at],
            ['Returned', $reservation->returned_at !== null || $status === \App\Enums\ReservationStatus::Returned, $reservation->returned_at],
        ];
    @endphp

    <div class="grid gap-5 md:grid-cols-2">
        <x-card>
            <h2 class="mb-4 break-words text-lg font-bold">{{ $reservation->equipment->name }}</h2>
            <dl class="grid min-w-0 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="stat-label">Lab</dt><dd class="break-words">{{ $reservation->equipment->lab->name }}</dd></div>
                <div><dt class="stat-label">Borrower</dt><dd class="break-words">{{ $reservation->user->name }}</dd></div>
                <div><dt class="stat-label">Starts</dt><dd>{{ $reservation->starts_at->format('M j, Y g:i A') }}</dd></div>
                <div><dt class="stat-label">Due</dt><dd>{{ $reservation->ends_at->format('M j, Y g:i A') }}</dd></div>
                @if ($reservation->approver)
                    <div><dt class="stat-label">Approved by</dt><dd class="break-words">{{ $reservation->approver->name }}</dd></div>
                @endif
                @if ($reservation->is_class_session)
                    <div><dt class="stat-label">Purpose</dt><dd>Faculty class session</dd></div>
                @endif
            </dl>
            <a class="mt-5 inline-block text-sm font-semibold text-blue-700 hover:underline" href="{{ route('equipment.show', $reservation->equipment) }}">View equipment</a>
        </x-card>
        <x-card>
            <h2 class="mb-4 text-lg font-bold">Reservation timeline</h2>
            <ol class="space-y-3 border-l-2 border-slate-200 pl-4 text-sm">
                @foreach ($steps as [$label, $reached, $time])
                    <li>
                        <p class="font-semibold">{{ $label }}</p>
                        <p class="{{ $reached ? 'text-emerald-800' : 'text-slate-600' }}">
                            {{ $time?->format('M j, Y g:i A') ?? ($reached ? 'Completed' : ($ended ? 'Not reached' : 'Waiting')) }}
                        </p>
                    </li>
                @endforeach
                @if ($ended)
                    <li class="font-semibold">{{ $status->label() }} · {{ $reservation->updated_at->format('M j, Y g:i A') }}</li>
                @endif
            </ol>
        </x-card>
    </div>
</x-app-layout>
