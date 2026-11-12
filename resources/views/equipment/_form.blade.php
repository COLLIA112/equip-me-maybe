@php $isEdit = isset($equipment); @endphp
<form method="POST" action="{{ $isEdit ? route('equipment.update', $equipment) : route('equipment.store') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @unless ($isEdit)
        <div>
            <label class="block text-sm font-medium">Lab</label>
            <select name="lab_id" class="mt-1 w-full rounded-md border-slate-300 text-sm" required>
                @foreach ($labs as $lab)<option value="{{ $lab->id }}" @selected(old('lab_id') == $lab->id)>{{ $lab->name }}</option>@endforeach
            </select>
        </div>
    @endunless

    <div>
        <label class="block text-sm font-medium">Name</label>
        <input name="name" value="{{ old('name', $equipment->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium">Serial number</label>
        <input name="serial_number" value="{{ old('serial_number', $equipment->serial_number ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium">Photo (JPG or PNG, max 2 MB)</label>
        <input type="file" name="photo" accept="image/png,image/jpeg" class="mt-1 block w-full text-sm">
    </div>
    <div>
        <p class="text-sm font-medium">Tags</p>
        @php $selected = old('tags', $isEdit ? $equipment->tags->pluck('id')->all() : []); @endphp
        <div class="mt-1 grid grid-cols-2 gap-1 sm:grid-cols-3">
            @foreach ($tags as $tag)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="rounded border-slate-300" @checked(in_array($tag->id, $selected))>
                    {{ $tag->name }}
                </label>
            @endforeach
        </div>
    </div>
    <div class="flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ $isEdit ? 'Save changes' : 'Create equipment' }}</button>
        <a href="{{ route('equipment.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</a>
    </div>
</form>
