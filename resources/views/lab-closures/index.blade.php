<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Laboratory closures</h1></x-slot>
    <x-card>
        <h2 class="text-lg font-semibold">Schedule a closure</h2>
        <p class="mt-2 text-sm text-slate-600">A closure blocks overlapping reservation requests and approvals for equipment in the laboratory. Existing approved reservations are retained; contact their borrowers to coordinate changes.</p>
        <form method="POST" action="{{ route('lab-closures.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <div><label for="closure-lab" class="block text-sm font-medium">Laboratory</label><select id="closure-lab" name="lab_id" required class="mt-1 w-full rounded-md border-slate-300">@foreach ($labs as $lab)<option value="{{ $lab->id }}" @selected(old('lab_id') == $lab->id)>{{ $lab->name }}</option>@endforeach</select></div>
            <div><label for="closure-start" class="block text-sm font-medium">Starts</label><input id="closure-start" name="starts_at" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('starts_at') }}" required class="mt-1 max-w-full w-full rounded-md border-slate-300"></div>
            <div><label for="closure-end" class="block text-sm font-medium">Ends</label><input id="closure-end" name="ends_at" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('ends_at') }}" required class="mt-1 max-w-full w-full rounded-md border-slate-300"></div>
            <div><label for="closure-reason" class="block text-sm font-medium">Reason</label><input id="closure-reason" name="reason" value="{{ old('reason') }}" maxlength="1000" required class="mt-1 w-full rounded-md border-slate-300"></div>
            <div><x-primary-button>Schedule closure</x-primary-button></div>
        </form>
    </x-card>
    <div class="mt-5 space-y-4">
        @forelse ($closures as $closure)
            <x-card>
                <h2 class="font-semibold">{{ $closure->lab->name }}</h2>
                <p class="mt-1 text-sm">{{ $closure->starts_at->format('M j, Y g:i A') }} to {{ $closure->ends_at->format('M j, Y g:i A') }}</p>
                <p class="mt-2 break-words text-sm text-slate-700">{{ $closure->reason }}</p>
                <p class="mt-1 text-sm text-slate-600">Scheduled by {{ $closure->creator?->name ?? 'Former user' }}</p>
                @if ($closure->ends_at->isFuture())
                    @php $started = $closure->starts_at->lte(now()); @endphp
                    <form method="POST" action="{{ route('lab-closures.update', $closure) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <div><label for="closure-lab-{{ $closure->id }}" class="block text-sm font-medium">Laboratory</label><select id="closure-lab-{{ $closure->id }}" name="lab_id" required @disabled($started) class="mt-1 w-full rounded-md border-slate-300">@foreach ($labs as $lab)<option value="{{ $lab->id }}" @selected($closure->lab_id === $lab->id)>{{ $lab->name }}</option>@endforeach</select>@if ($started)<input type="hidden" name="lab_id" value="{{ $closure->lab_id }}">@endif</div>
                        <div><label for="closure-start-{{ $closure->id }}" class="block text-sm font-medium">Starts</label><input id="closure-start-{{ $closure->id }}" name="starts_at" type="datetime-local" step="1" value="{{ $closure->starts_at->format('Y-m-d\TH:i:s') }}" required @readonly($started) class="mt-1 max-w-full w-full rounded-md border-slate-300"></div>
                        <div><label for="closure-end-{{ $closure->id }}" class="block text-sm font-medium">Ends</label><input id="closure-end-{{ $closure->id }}" name="ends_at" type="datetime-local" value="{{ $closure->ends_at->format('Y-m-d\TH:i') }}" required class="mt-1 max-w-full w-full rounded-md border-slate-300"></div>
                        <div><label for="closure-reason-{{ $closure->id }}" class="block text-sm font-medium">Reason</label><input id="closure-reason-{{ $closure->id }}" name="reason" value="{{ $closure->reason }}" maxlength="1000" required class="mt-1 w-full rounded-md border-slate-300"></div>
                        <div><x-primary-button>Save closure</x-primary-button></div>
                    </form>
                @endif
                <form method="POST" action="{{ route('lab-closures.destroy', $closure) }}" data-confirm="Remove this laboratory closure?" class="mt-4">@csrf @method('DELETE')<x-danger-button>Remove closure</x-danger-button></form>
            </x-card>
        @empty
            <x-empty-state>No closures scheduled for your managed laboratories.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-5">{{ $closures->links() }}</div>
</x-app-layout>
