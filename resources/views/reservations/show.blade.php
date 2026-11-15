<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Reservation #{{ $reservation->id }}</h1>
        <x-badge :status="$reservation->status" />
    </x-slot>

    @php
        $status = $reservation->status;
        $ended = in_array($status, [\App\Enums\ReservationStatus::Rejected, \App\Enums\ReservationStatus::Cancelled], true);
        $steps = [
            ['Requested', true, $reservation->created_at, $reservation->user?->name],
            ['Approved', $reservation->approved_by !== null || in_array($status, [\App\Enums\ReservationStatus::Approved, \App\Enums\ReservationStatus::CheckedOut, \App\Enums\ReservationStatus::Returned], true), $reservation->approved_at, $reservation->approver?->name],
            ['Checked out', $reservation->handed_over_at !== null || $status === \App\Enums\ReservationStatus::CheckedOut, $reservation->handed_over_at, $reservation->handoverManager?->name],
            ['Returned', $reservation->returned_at !== null || $status === \App\Enums\ReservationStatus::Returned, $reservation->returned_at, $reservation->returnManager?->name],
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
            @if ($reservation->rejection_reason)<p role="note" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800"><strong>Rejection reason:</strong> {{ $reservation->rejection_reason }}</p>@endif
            @if ($reservation->isOverdue())<p class="mt-3 font-semibold text-red-800">Overdue</p>@endif
            <a class="mt-5 inline-block text-sm font-semibold text-blue-700 hover:underline" href="{{ route('equipment.show', $reservation->equipment) }}">View equipment</a>
        </x-card>
        <x-card>
            <h2 class="mb-4 text-lg font-bold">Reservation timeline</h2>
            <ol class="space-y-3 border-l-2 border-slate-200 pl-4 text-sm">
                @foreach ($steps as [$label, $reached, $time, $actor])
                    <li>
                        <p class="font-semibold">{{ $label }}</p>
                        <p class="{{ $reached ? 'text-emerald-800' : 'text-slate-600' }}">
                            {{ $time?->format('M j, Y g:i A') ?? ($reached ? 'Completed; time not recorded' : ($ended ? 'Not reached' : 'Waiting')) }}
                        </p>
                        @if ($reached)<p class="text-slate-600">{{ $actor ? "By ".$actor : "Actor not recorded" }}</p>@endif
                    </li>
                @endforeach
                @if ($ended)
                    <li class="font-semibold">{{ $status->label() }} · {{ ($status === \App\Enums\ReservationStatus::Rejected ? $reservation->rejected_at : $reservation->cancelled_at)?->format('M j, Y g:i A') ?? 'Time not recorded' }}
                        <p class="text-sm font-normal text-slate-600">{{ ($status === \App\Enums\ReservationStatus::Rejected ? $reservation->rejector?->name : $reservation->canceller?->name) ?? ($status === \App\Enums\ReservationStatus::Rejected && $reservation->rejection_reason === \App\Services\ReservationService::FACULTY_PRIORITY_REASON ? 'Faculty priority rule' : 'Actor not recorded') }}</p>
                    </li>
                @endif
            </ol>
        </x-card>
    </div>
</x-app-layout>
