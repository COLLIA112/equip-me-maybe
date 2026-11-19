
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
        if (Route::has('wishlist.index')) {
            $links[] = ['Wishlist', route('wishlist.index'), 'wishlist.*'];
        }
    }

    if ($isManager) {
        $links[] = ['Reservation Queue', route('reservations.queue'), 'reservations.queue'];
        if (Route::has('reservations.counter')) {
            $links[] = ['Handover counter', route('reservations.counter'), 'reservations.counter*'];
        }
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }

    if ($isManager || $isHead) {
        $links[] = ['Tags', route('tags.index'), 'tags.*'];
        if (Route::has('equipment.tools.index')) {
            $links[] = ['Equipment tools', route('equipment.tools.index'), 'equipment.tools.*'];
        }
        if (Route::has('lab-closures.index')) {
            $links[] = ['Lab closures', route('lab-closures.index'), 'lab-closures.*'];
        }
    }

    if ($isHead) {
        $links[] = ['Department', route('department.index'), 'department.index|department.breakdowns'];
        $links[] = ['Users and Roles', route('department.users'), 'department.users'];
        $links[] = ['Laboratories', route('department.labs.index'), 'department.labs.*'];
        $links[] = ['Maintenance', route('maintenance.index'), 'maintenance.*'];
    }

    if (Route::has('activities.index')) {
        $links[] = ['Activity history', route('activities.index'), 'activities.*'];
    }
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebar"
     x-cloak
     @click="closeMenu()"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"
     aria-hidden="true"></div>

<aside
    :data-open="sidebar.toString()"
    class="workspace-sidebar invisible fixed inset-y-0 left-0 z-50 flex max-w-full -translate-x-full flex-col bg-[#10223f] text-white motion-safe:transition-transform motion-safe:duration-200 data-[open=true]:visible data-[open=true]:translate-x-0 lg:visible lg:sticky lg:translate-x-0 print:hidden">

    <div class="flex min-h-16 items-center justify-between gap-2 border-b border-white/10 px-4">
        <a class="inline-flex min-h-11 items-center rounded-md font-bold tracking-tight lg:text-lg" href="{{ route('dashboard') }}">
            Equip Me Maybe
        </a>
        <button type="button"
                x-ref="closeMenu"
                @click="closeMenu()"
                class="min-h-11 rounded-md border border-slate-500 px-3 text-sm lg:hidden"
                aria-label="Close navigation">
            Close
        </button>
    </div>

    <div id="workspace-navigation"
         class="flex-1 overflow-y-auto overscroll-y-contain p-4">

        <nav aria-label="Main navigation"
             class="space-y-1">

            @foreach ($links as [$label, $url, $pattern])
                @php
                    $active = request()->routeIs(...explode('|', $pattern));
                @endphp

                <a href="{{ $url }}"
                   @if ($active) aria-current="page" @endif
                   class="flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold {{ $active ? 'bg-blue-700 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-5 border-t border-white/15 pt-4 lg:mt-auto">
            <div role="img"
                 class="mb-2 ml-3 flex h-10 w-10 items-center justify-center rounded-full bg-blue-700 text-sm font-bold text-white"
                 aria-label="{{ $u->name }} initials avatar">
                {{ collect(preg_split('/\s+/u', trim($u->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}
            </div>

            <p class="break-words px-3 text-sm font-semibold">{{ $u->name }}</p>
            <p class="px-3 pb-2 text-xs text-slate-300">{{ $u->role->label() }}</p>

            <a href="{{ route('profile.edit') }}"
               @if (request()->routeIs('profile.*')) aria-current="page" @endif
               class="flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-white/10 {{ request()->routeIs('profile.*') ? 'bg-blue-700' : 'text-slate-200' }}">
                Profile
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="min-h-11 w-full rounded-lg px-3 text-left text-sm text-slate-200 hover:bg-white/10">
                    Log out
                </button>
            </form>
        </div>
    </div>
</aside>
