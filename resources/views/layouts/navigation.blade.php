@php
    $u = auth()->user();
    $isManager = $u->hasRole(\App\Enums\Role::LabManager);
    $isHead = $u->hasRole(\App\Enums\Role::DepartmentHead);
    $links = [];
    if (! $u->hasRole(\App\Enums\Role::SecurityAuditor)) {
        if (! $isHead) {
            $links[] = ['Dashboard', route('dashboard'), 'dashboard'];
        }
        $links[] = ['Equipment', route('equipment.index'), 'equipment.*'];
    }
    if ($u->isBorrower()) {
        $links[] = ['My Reservations', route('reservations.index'), 'reservations.index|reservations.show'];
        $links[] = ['Required Documents', route('documents.checklist'), 'documents.checklist'];
        if (Route::has('wishlist.index')) $links[] = ['Wishlist', route('wishlist.index'), 'wishlist.*'];
    }
    if ($isManager) {
        $links[] = ['Reservation Queue', route('reservations.queue'), 'reservations.queue'];
        if (Route::has('reservations.counter')) $links[] = ['Handover counter', route('reservations.counter'), 'reservations.counter*'];
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }
    if ($isManager || $isHead) {
        $links[] = ['Tags', route('tags.index'), 'tags.*'];
        if (Route::has('equipment.tools.index')) $links[] = ['Equipment tools', route('equipment.tools.index'), 'equipment.tools.*'];
        if (Route::has('lab-closures.index')) $links[] = ['Lab closures', route('lab-closures.index'), 'lab-closures.*'];
    }
    if ($isHead) {
        $links[] = ['Department', route('department.index'), 'department.index|department.breakdowns'];
        $links[] = ['Users and Roles', route('department.users'), 'department.users'];
        $links[] = ['Laboratories', route('department.labs.index'), 'department.labs.*'];
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }
    if (Route::has('activities.index')) $links[] = ['Activity history', route('activities.index'), 'activities.*'];
@endphp
<aside class="min-w-0 bg-[#10223f] text-white print:hidden"
       x-data="{ open: false }"
       @keydown.escape="if (open) { open = false; $refs.menu.focus() }">
    <div class="flex min-h-16 items-center justify-between gap-2 px-4 lg:hidden">
        <a class="font-bold tracking-tight" href="{{ route('dashboard') }}">Equip Me Maybe</a>
        <button type="button" x-ref="menu" @click="open = !open" aria-expanded="false" :aria-expanded="open.toString()" :aria-label="open ? 'Close navigation' : 'Open navigation'"
                aria-controls="workspace-navigation" class="rounded-md border border-slate-500 px-3 py-2 text-sm">Menu</button>
    </div>
    <div id="workspace-navigation" class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-white/10 p-4 lg:sticky lg:top-0 lg:flex lg:h-screen lg:max-h-screen lg:flex-col lg:border-t-0"
         :class="open ? 'block' : 'hidden'" x-cloak>
        <a class="mb-8 hidden px-2 pt-3 text-xl font-bold tracking-tight lg:block" href="{{ route('dashboard') }}">Equip Me Maybe</a>

        <nav aria-label="Main navigation" class="space-y-1" @click="if ($event.target.closest('a')) open = false">
            @foreach ($links as [$label, $url, $pattern])
                @php $active = request()->routeIs(...explode('|', $pattern)); @endphp
                <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                   class="block rounded-lg px-3 py-2.5 text-sm font-semibold {{ $active ? 'bg-blue-700 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mt-5 border-t border-white/15 pt-4 lg:mt-auto">
            <div role="img" class="mb-2 ml-3 flex h-10 w-10 items-center justify-center rounded-full bg-blue-700 text-sm font-bold text-white" aria-label="{{ $u->name }} initials avatar">{{ collect(preg_split('/\s+/u', trim($u->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</div>
            <p class="break-words px-3 text-sm font-semibold">{{ $u->name }}</p>
            <p class="px-3 pb-2 text-xs text-slate-300">{{ $u->role->label() }}</p>
            <a href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*')) aria-current="page" @endif
               class="block rounded-lg px-3 py-2 text-sm hover:bg-white/10 {{ request()->routeIs('profile.*') ? 'bg-blue-700' : 'text-slate-200' }}">Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-200 hover:bg-white/10">Log out</button>
            </form>
        </div>
    </div>
</aside>
