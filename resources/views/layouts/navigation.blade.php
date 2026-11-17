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

{{-- mobile backdrop --}}
<div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

<aside
    :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 flex flex-col bg-slate-900 text-slate-100 transition-transform lg:static lg:translate-x-0 lg:min-h-screen">
    <div class="flex items-center justify-between h-14 px-5 border-b border-slate-800">
        <a href="{{ route('dashboard') }}" class="font-semibold tracking-tight text-white">Equip Me Maybe</a>
        <button @click="sidebar = false" class="lg:hidden p-1 rounded hover:bg-slate-800" aria-label="Close menu">✕</button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="Main">
        @foreach ($links as [$label, $url, $pattern])
            <a href="{{ $url }}"
               @if (request()->routeIs($pattern)) aria-current="page" @endif
               class="block px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 {{ request()->routeIs($pattern) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-800 px-5 py-4 text-sm">
        <a href="{{ route('profile.edit') }}" class="block text-slate-200 hover:text-white">
            {{ $u->name }}
            <span class="block text-xs text-slate-400">{{ $u->role->label() }}</span>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button class="text-slate-300 hover:text-white">Log out</button>
        </form>
    </div>
</aside>