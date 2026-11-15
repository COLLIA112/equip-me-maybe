<form method="GET" data-instant-filters class="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
    <div>
        <label for="report-period" class="block text-sm font-semibold">Report period</label>
        <select id="report-period" name="period" onchange="this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm">
            @foreach(['week' => 'This week', 'month' => 'This month', 'semester' => 'This semester', 'custom' => 'Custom dates'] as $value => $label)
                <option value="{{ $value }}" @selected($range['period'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div><label for="report-from" class="block text-sm font-semibold">From</label><input id="report-from" name="from" type="date" value="{{ $range['from'] }}" onchange="this.form.elements.namedItem('period').value='custom';this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm"></div>
    <div><label for="report-to" class="block text-sm font-semibold">Through</label><input id="report-to" name="to" type="date" value="{{ $range['to'] }}" min="{{ $range['from'] }}" onchange="this.form.elements.namedItem('period').value='custom';this.form.requestSubmit()" class="mt-1 rounded-md border-slate-300 text-sm"></div>
    @if($range['period'] !== 'month')<a href="{{ route('department.index', ['clear' => 1]) }}" class="text-sm font-semibold text-blue-700 underline">Clear filter</a>@endif
    <noscript><button class="rounded bg-blue-700 px-3 py-2 text-sm text-white">Update report</button></noscript>
    <p class="w-full text-xs text-slate-600">Semesters run January–June and July–December. Custom ranges support up to 366 days. Invalid dates use this month.</p>
</form>
