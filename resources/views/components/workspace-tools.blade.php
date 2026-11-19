@php
    $viewer = auth()->user();
    $preferences = $viewer->preferences ?? [];
    $theme = in_array($preferences['theme'] ?? 'system', ['light', 'dark', 'system'], true) ? ($preferences['theme'] ?? 'system') : 'system';
    $unread = $viewer->unreadNotifications()->count();
    $routeName = request()->route()?->getName() ?? '';
    $listName = match (true) {
        str_starts_with($routeName, 'equipment.'), str_starts_with($routeName, 'documents.') => 'equipment.index',
        str_starts_with($routeName, 'reservations.') => $viewer->isBorrower() ? 'reservations.index' : ($viewer->hasRole(\App\Enums\Role::LabManager) ? 'reservations.queue' : 'department.index'),
        str_starts_with($routeName, 'maintenance.') => 'maintenance.index',
        str_starts_with($routeName, 'department.') => 'department.index',
        default => null,
    };
    $back = $listName ? session('list_back.'.$viewer->id.'.'.$listName, route($listName, [], false)) : null;
    // Session values are only accepted as local paths, including after session migration.
    if ($back && (! str_starts_with($back, '/') || str_starts_with($back, '//') || str_contains($back, '\\'))) {
        $back = route($listName, [], false);
    }
@endphp
<div class="border-b border-slate-200 bg-white print:hidden">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        @if ($routeName !== 'equipment.index' || request()->filled('model'))
            <form method="GET" action="{{ route('equipment.index') }}" role="search" aria-label="All equipment" class="flex min-w-0 flex-1 gap-2 sm:max-w-md">
                <label for="global-search" class="sr-only">Search equipment by name, serial or code</label>
                <input id="global-search" data-global-search type="search" name="q" placeholder="Search equipment · /" maxlength="150" class="min-h-11 min-w-0 w-full rounded-md border-slate-300 text-base sm:text-sm" aria-keyshortcuts="/">
                <button type="submit" class="min-h-11 rounded-md bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Search</button>
            </form>
        @endif
        <div class="ml-auto flex flex-wrap items-center gap-3 text-sm">
            @if (Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}" class="inline-flex min-h-11 items-center gap-1 rounded px-1 font-semibold text-blue-700" aria-label="Notifications, {{ $unread }} unread">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
                    <span>Notifications</span><span class="min-w-6 rounded-full bg-blue-100 px-2 py-0.5 text-center text-xs tabular-nums text-blue-800">{{ $unread }}</span>
                </a>
            @endif
            <details class="relative">
                <summary class="min-h-11 cursor-pointer rounded px-1 py-3 font-semibold text-blue-700">Display settings</summary>
                <form method="POST" action="{{ route('preferences.update') }}" class="absolute right-0 z-30 mt-2 w-64 rounded-lg border border-slate-200 bg-white p-4 shadow-lg">
                    @csrf @method('PATCH')
                    <label for="display-theme" class="block font-medium">Color theme</label>
                    <select id="display-theme" name="theme" class="mt-1 min-h-11 w-full rounded-md border-slate-300 text-base sm:text-sm">
                        @foreach (['system' => 'Use device setting', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                            <option value="{{ $value }}" @selected($theme === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="remember_filters" value="0">
                    <label class="mt-3 flex items-start gap-2"><input type="checkbox" name="remember_filters" value="1" @checked($preferences['remember_filters'] ?? true) class="mt-1 rounded border-slate-300"><span>Remember my sort and filter choices</span></label>
                    <button type="submit" class="mt-3 min-h-11 rounded-md bg-blue-700 px-3 font-semibold text-white">Save preferences</button>
                </form>
            </details>
        </div>
    </div>
    <nav aria-label="Breadcrumb" class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 px-4 pb-3 text-sm text-slate-600 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="font-semibold text-blue-700 hover:underline">Home</a>
        @if ($listName)
            <span aria-hidden="true">/</span>
            <a href="{{ $back }}" class="font-semibold text-blue-700 hover:underline">{{ match ($listName) { 'equipment.index' => 'Equipment', 'reservations.index' => 'My reservations', 'reservations.queue' => 'Reservation queue', 'maintenance.index' => 'Maintenance', default => 'Department' } }}</a>
            @if ($routeName !== $listName)
                <span aria-hidden="true">/</span><span aria-current="page">{{ str($routeName)->afterLast('.')->replace('-', ' ')->headline() }}</span>
                <a href="{{ $back }}" class="ml-auto font-semibold text-blue-700 hover:underline">Back to list</a>
            @endif
        @else
            <span aria-hidden="true">/</span><span aria-current="page">{{ str($routeName)->before('.')->headline() }}</span>
        @endif
    </nav>
</div>
