<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-blue-700">{{ $role->label() }}</p>
            <h1 class="page-title break-words">Welcome, {{ auth()->user()->name }}</h1>
        </div>
    </x-slot>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $label => $value)
                <x-card><p class="stat-label">{{ $label }}</p><p class="stat-number">{{ $value }}</p></x-card>
            @endforeach
        </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <x-card>
                <h2 class="mb-4 text-lg font-bold">Recent reservations</h2>
                @forelse ($recent as $reservation)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 py-3 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('reservations.show', $reservation) }}" class="break-words font-semibold text-blue-700 hover:underline">{{ $reservation->equipment->name }}</a>
                            <p class="text-slate-600">{{ $reservation->starts_at->format('M j, Y g:i A') }}</p>
                            @if ($role === \App\Enums\Role::LabManager)
                                <p class="break-words text-slate-600">{{ $reservation->user->name }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2"><x-badge :status="$reservation->status" />
                        @if($reservation->status === \App\Enums\ReservationStatus::CheckedOut && $reservation->ends_at->isPast())
                            <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Overdue</span>
                        @endif</div>
                    </div>
                @empty
                    <x-empty-state>There is no reservation activity yet.</x-empty-state>
                @endforelse
            </x-card>
            <x-card>
                <h2 class="mb-3 text-lg font-bold">Quick actions</h2>
                <ul class="space-y-3 text-sm font-semibold text-blue-700">
                    <li><a class="hover:underline" href="{{ route('equipment.index') }}">Browse equipment</a></li>
                    @if (auth()->user()->isBorrower())
                        <li><a class="hover:underline" href="{{ route('reservations.index') }}">My reservations</a></li>
                        <li><a class="hover:underline" href="{{ route('documents.checklist') }}">Required documents</a></li>
                    @else
                        <li><a class="hover:underline" href="{{ route('reservations.queue', ['status' => 'pending']) }}">Requests needing approval</a></li>
                        <li><a class="hover:underline" href="{{ route('reservations.queue', ['overdue' => 1]) }}">Overdue returns</a></li>
                        <li><a class="hover:underline" href="{{ route('reservations.queue') }}">Open reservation queue</a></li>
                        <li><a class="hover:underline" href="{{ route('equipment.create') }}">Add equipment</a></li>
                        <li><a class="hover:underline" href="{{ route('maintenance.index') }}">Report a breakdown</a></li>
                    @endif
                </ul>
            </x-card>
        </div>
        @if (auth()->user()->isBorrower())
            <x-card class="mt-5">
                <h2 class="text-lg font-bold">Documents still to acknowledge</h2>
                <p class="mt-2 text-sm text-slate-700">{{ $documentAcknowledged }} of {{ $documentTotal }} acknowledged</p>
                <progress class="mt-2 h-3 w-full accent-blue-700" value="{{ $documentAcknowledged }}" max="{{ max(1, $documentTotal) }}" aria-label="Required document acknowledgement progress">{{ $documentAcknowledged }} of {{ $documentTotal }}</progress>
                @forelse($pendingDocuments as $document)
                    <p class="mt-2 text-sm"><a class="font-semibold text-blue-700 underline" href="{{ route('documents.checklist') }}">{{ $document->title }}</a> <span class="text-slate-600">for {{ $document->equipment->name }}</span></p>
                @empty
                    <p class="mt-2 text-sm text-green-800">All required documents for your open reservations are acknowledged.</p>
                @endforelse
                <a class="mt-3 inline-block text-sm font-semibold text-blue-700 underline" href="{{ route('documents.checklist') }}">Open document checklist</a>
            </x-card>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <x-card>
                    <h2 class="text-lg font-bold">Upcoming reservations</h2>
                    @forelse($upcoming as $reservation)
                        <p class="mt-3 text-sm"><a class="font-semibold text-blue-700 underline" href="{{ route('reservations.show', $reservation) }}">{{ $reservation->equipment->name }}</a><br>{{ $reservation->starts_at->format('M j, Y g:i A') }}</p>
                    @empty
                        <x-empty-state>You have no upcoming approved reservations.</x-empty-state>
                    @endforelse
                </x-card>
                <x-card>
                    <h2 class="text-lg font-bold">Active checkouts</h2>
                    @forelse($active as $reservation)
                        <p class="mt-3 text-sm"><a class="font-semibold text-blue-700 underline" href="{{ route('reservations.show', $reservation) }}">{{ $reservation->equipment->name }}</a><br>Due {{ $reservation->ends_at->format('M j, Y g:i A') }}
                        @if($reservation->ends_at->isPast())<span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Overdue</span>@endif</p>
                    @empty
                        <x-empty-state>You have no active checkouts.</x-empty-state>
                    @endforelse
                </x-card>
            </div>
        @endif
        @if ($role === \App\Enums\Role::LabManager)
            <x-card class="mt-5">
                <h2 class="mb-3 text-lg font-bold">Recent maintenance</h2>
                @forelse ($recentMaintenance as $log)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 py-3 text-sm">
                        <div class="min-w-0">
                            <a class="break-words font-semibold text-blue-700 hover:underline" href="{{ route('equipment.show', $log->equipment) }}">{{ $log->equipment->name }}</a>
                            <p class="break-words text-slate-700">{{ $log->issue }}</p>
                        </div>
                        <x-badge :status="$log->resolved_at ? 'completed' : 'under_repair'" />
                    </div>
                @empty
                    <x-empty-state>No maintenance activity yet.</x-empty-state>
                @endforelse
            </x-card>
        @endif

</x-app-layout>
