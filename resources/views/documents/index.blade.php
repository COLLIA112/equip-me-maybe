<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Documents for {{ $equipment->name }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-3 font-medium">Upload PDF</h2>
            <form method="POST" action="{{ route('documents.store', $equipment) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input aria-label="Document title" name="title" value="{{ old('title') }}" placeholder="Title" required class="w-full rounded-md border-slate-300 text-sm">
                <select aria-label="Document type" name="type" class="w-full rounded-md border-slate-300 text-sm">
                    @foreach (\App\Models\Document::TYPES as $t)<option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>@endforeach
                </select>
                <input type="file" aria-label="PDF document" name="file" accept="application/pdf" required class="block w-full text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" class="rounded border-slate-300"> Required before handover</label>
                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Upload</button>
                <p class="text-xs text-slate-500">PDF only, up to 5 MB.</p>
            </form>
        </x-card>
        <x-card class="lg:col-span-2">
            <h2 class="mb-3 font-medium">Library</h2>
            <x-document-filters :filters="$filters" :sort="$sort" :direction="$direction" :clear-url="route('documents.index', $equipment)" />
            <x-list-summary :paginator="$documents" />
            @forelse ($documents as $doc)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t first:border-t-0 py-2 text-sm">
                    <div>
                        <p class="font-medium">{{ $doc->title }}
                            @if ($doc->is_required)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Required</span>@endif</p>
                        <p class="text-slate-500">{{ ucfirst(str_replace('_', ' ', $doc->type)) }} · {{ $doc->original_name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('documents.download', $doc) }}" class="text-blue-600 hover:underline">Download</a>
                        <form method="POST" action="{{ route('documents.destroy', $doc) }}" data-confirm="Delete this document?">
                            @csrf @method('DELETE')<button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state>{{ count($filters) ? 'No documents match your filters. Clear the filters to see the full library.' : 'No documents have been uploaded for this equipment yet.' }}</x-empty-state>
            @endforelse
            <div class="mt-4">{{ $documents->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
