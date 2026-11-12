@php
    $u = auth()->user();
    $isManager = $u->hasRole(\App\Enums\Role::LabManager);
    $isHead = $u->hasRole(\App\Enums\Role::DepartmentHead);
    $links = [];
    if (! $u->hasRole(\App\Enums\Role::SecurityAuditor)) {
        $links[] = ['Dashboard', route('dashboard'), 'dashboard'];
        $links[] = ['Equipment', route('equipment.index'), 'equipment.*'];
    }
    if ($u->isBorrower()) {
        $links[] = ['My Reservations', route('reservations.index'), 'reservations.index'];
        $links[] = ['Required Documents', route('documents.checklist'), 'documents.checklist'];
    }
    if ($isManager) {
        $links[] = ['Reservation Queue', route('reservations.queue'), 'reservations.queue'];
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }
    if ($isHead) {
        $links[] = ['Department', route('department.index'), 'department.index'];
        $links[] = ['Users and Roles', route('department.users'), 'department.users'];
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }
@endphp
<nav x-data="{ open: false }" class="bg-slate-900 text-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 items-center">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="font-semibold tracking-tight text-white">Equip Me Maybe</a>
                <div class="hidden md:flex gap-1">
                    @foreach ($links as [$label, $url, $pattern])
                        <a href="{{ $url }}"
                           class="px-3 py-1.5 rounded-md text-sm {{ request()->routeIs($pattern) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div class="hidden md:flex items-center gap-4 text-sm">
                <a href="{{ route('profile.edit') }}" class="text-slate-300 hover:text-white">
                    {{ $u->name }} <span class="text-slate-400">({{ $u->role->label() }})</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-slate-300 hover:text-white">Log out</button>
                </form>
            </div>
            <button @click="open = ! open" class="md:hidden p-2 rounded-md hover:bg-slate-800" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div x-show="open" x-cloak class="md:hidden border-t border-slate-700 px-4 py-3 space-y-1">
        @foreach ($links as [$label, $url, $pattern])
            <a href="{{ $url }}" class="block px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-slate-800">{{ $label }}</a>
        @endforeach
        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-slate-800">Profile ({{ $u->name }})</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="block w-full text-left px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-slate-800">Log out</button>
        </form>
    </div>
</nav>
