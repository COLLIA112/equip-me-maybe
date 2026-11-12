<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Documents for {{ $equipment->name }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-3 font-medium">Upload PDF</h2>
            <form method="POST" action="{{ route('documents.store', $equipment) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input name="title" value="{{ old('title') }}" placeholder="Title" required class="w-full rounded-md border-slate-300 text-sm">
                <select name="type" class="w-full rounded-md border-slate-300 text-sm">
                    @foreach (\App\Models\Document::TYPES as $t)<option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>@endforeach
                </select>
                <input type="file" name="file" accept="application/pdf" required class="block w-full text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" class="rounded border-slate-300"> Required before handover</label>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Upload</button>
                <p class="text-xs text-slate-500">PDF only, up to 5 MB.</p>
            </form>
        </x-card>
        <x-card class="lg:col-span-2">
            <h2 class="mb-3 font-medium">Library</h2>
            @forelse ($equipment->documents as $doc)
                <div class="flex items-center justify-between border-t first:border-t-0 py-2 text-sm">
                    <div>
                        <p class="font-medium">{{ $doc->title }}
                            @if ($doc->is_required)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Required</span>@endif</p>
                        <p class="text-slate-500">{{ ucfirst(str_replace('_', ' ', $doc->type)) }} · {{ $doc->original_name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('documents.download', $doc) }}" class="text-indigo-600 hover:underline">Download</a>
                        <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Delete this document?')">
                            @csrf @method('DELETE')<button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No documents yet.</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
