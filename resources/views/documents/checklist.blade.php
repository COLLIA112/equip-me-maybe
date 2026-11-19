<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Required documents</h1></x-slot>
    <p class="mb-4 text-sm text-slate-600">Download each document first, then acknowledge it. Your lab manager cannot approve or hand over equipment until this is done.</p>
    <x-document-filters :filters="$filters" :sort="$sort" :direction="$direction" :clear-url="route('documents.checklist', ['clear' => 1])" :checklist="true" />
    <x-list-summary :paginator="$documents" />
    <div class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-2 text-sm font-semibold">{{ $checklistProgress['acknowledged'] }} of {{ $checklistProgress['total'] }} matching documents acknowledged</p>
        <progress class="h-3 w-full accent-blue-700" value="{{ $checklistProgress['acknowledged'] }}" max="{{ max(1, $checklistProgress['total']) }}" aria-label="Filtered document acknowledgement progress">{{ $checklistProgress['acknowledged'] }} of {{ $checklistProgress['total'] }}</progress>
    </div>
    <div class="space-y-3">
        @forelse ($documents as $doc)
            @php $pivot = $doc->users->first()?->pivot; @endphp
            <x-card class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ $doc->title }}</p>
                    <p class="text-sm text-slate-500">For <a href="{{ route('equipment.show', $doc->equipment) }}" class="text-blue-700 underline">{{ $doc->equipment->name }}</a></p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('documents.download', $doc) }}" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Download</a>
                    @if ($pivot?->acknowledged_at)
                        <span class="rounded-full bg-green-100 px-3 py-1 text-green-800">Acknowledged</span>
                    @else
                        <form method="POST" action="{{ route('documents.acknowledge', $doc) }}">
                            @csrf
                            <button @disabled(! $pivot?->downloaded_at) class="rounded-md bg-blue-600 px-3 py-1.5 text-white disabled:cursor-not-allowed disabled:opacity-40">I have read this</button>
                        </form>
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state>{{ count($filters) ? 'No documents match your filters for your open reservations. Clear the filters to view your required checklist.' : 'No required documents for your open reservations.' }}</x-empty-state>
        @endforelse
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-app-layout>
