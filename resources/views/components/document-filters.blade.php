@props(['filters', 'sort', 'direction', 'clearUrl', 'checklist' => false])
<form method="GET" data-instant-filters class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
    <div>
        <label for="filter-required" class="block text-sm font-medium">Required status</label>
        <select id="filter-required" name="required" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
            <option value="{{ $checklist ? 'required' : '' }}" @selected(($filters['required'] ?? ($checklist ? 'required' : 'all')) === ($checklist ? 'required' : 'all'))>{{ $checklist ? 'Required documents' : 'All documents' }}</option>
            @if ($checklist)<option value="all" @selected(($filters['required'] ?? '') === 'all')>All documents</option>@else<option value="required" @selected(($filters['required'] ?? '') === 'required')>Required documents</option>@endif
            <option value="optional" @selected(($filters['required'] ?? '') === 'optional')>Optional documents</option>
        </select>
    </div>
    <div>
        <label for="filter-acknowledged" class="block text-sm font-medium">{{ $checklist ? 'Your acknowledgement' : 'Borrower acknowledgements' }}</label>
        <select id="filter-acknowledged" name="acknowledged" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
            <option value="">Any acknowledgement</option>
            <option value="yes" @selected(($filters['acknowledged'] ?? '') === 'yes')>{{ $checklist ? 'Acknowledged' : 'Acknowledged by a borrower' }}</option>
            <option value="no" @selected(($filters['acknowledged'] ?? '') === 'no')>{{ $checklist ? 'Still to acknowledge' : 'No borrower acknowledgements' }}</option>
        </select>
    </div>
    <div>
        <label for="filter-sort" class="block text-sm font-medium">Sort by</label>
        <select id="filter-sort" name="sort" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
            @foreach (['title' => 'Title', 'is_required' => 'Required status', 'created_at' => 'Date uploaded'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="filter-direction" class="block text-sm font-medium">Order</label>
        <select id="filter-direction" name="direction" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm"><option value="asc" @selected($direction === 'asc')>Ascending</option><option value="desc" @selected($direction === 'desc')>Descending</option></select>
    </div>
    @if (count(array_filter($filters, fn ($value) => $value !== null && $value !== '')))
        <a href="{{ $clearUrl }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-blue-700 hover:bg-slate-50">Clear filter</a>
    @endif
</form>
