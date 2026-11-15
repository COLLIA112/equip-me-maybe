@if (session('status'))
    <div role="status" aria-live="polite" aria-atomic="true" data-flash class="mb-4 flex items-start justify-between gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <p>{{ session('status') }}</p>
        <button type="button" data-dismiss-flash aria-label="Dismiss notification" class="shrink-0 rounded px-1 font-semibold underline">Dismiss</button>
    </div>
@endif
@if ($errors->any())
    <div role="alert" aria-atomic="true" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
