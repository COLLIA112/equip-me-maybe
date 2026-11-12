<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Required documents</h1></x-slot>
    <p class="mb-4 text-sm text-slate-600">Download each document first, then acknowledge it. Your lab manager cannot approve or hand over equipment until this is done.</p>
    <div class="space-y-3">
        @forelse ($documents as $doc)
            @php $pivot = $doc->users->first()?->pivot; @endphp
            <x-card class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ $doc->title }}</p>
                    <p class="text-sm text-slate-500">For {{ $doc->equipment->name }}</p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('documents.download', $doc) }}" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Download</a>
                    @if ($pivot?->acknowledged_at)
                        <span class="rounded-full bg-green-100 px-3 py-1 text-green-800">Acknowledged</span>
                    @else
                        <form method="POST" action="{{ route('documents.acknowledge', $doc) }}">
                            @csrf
                            <button @disabled(! $pivot?->downloaded_at) class="rounded-md bg-indigo-600 px-3 py-1.5 text-white disabled:cursor-not-allowed disabled:opacity-40">I have read this</button>
                        </form>
                    @endif
                </div>
            </x-card>
        @empty
            <x-card class="text-center text-sm text-slate-500">No required documents for your open reservations.</x-card>
        @endforelse
    </div>
</x-app-layout>
