
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

    $icons = [
        'Dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',

        'Equipment' => 'M12 3 3 8l9 5 9-5-9-5z M3 8v8l9 5 9-5V8 M12 13v8',

        'My Reservations' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2z',

        'Required Documents' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8',

        'Wishlist' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z',

        'Activity history' => 'M3 12a9 9 0 1 0 3-6.7 M3 4v6h6 M12 7v5l3 2',

        'Reservation Queue' => 'M8 6h13 M8 12h13 M8 18h13 M3 6h1 M3 12h1 M3 18h1',

        'Handover counter' => 'M3 7h18 M3 17h18 M7 3l-4 4 4 4 M17 13l4 4-4 4',

        'Maintenance' => 'M14 6l4 4 M5 19l9-9 M16 3a5 5 0 0 0-6 6L3 16v5h5l7-7a5 5 0 0 0 6-6l-4 3-4-4 3-4z',

        'Tags' => 'M3 3h8l10 10-8 8L3 11V3z M7.5 7.5h.01',

        'Equipment tools' => 'M4 7h16v14H4z M9 7V3h6v4 M4 12h16',

        'Lab closures' => 'M8 2v4 M16 2v4 M3 10h18 M3 4h18v17H3z M9 14l6 5 M15 14l-6 5',

        'Department' => 'M3 21V7l9-4 9 4v14 M9 21v-5h6v5 M7 10h2 M15 10h2',

        'Users and Roles' => 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M17 5a4 4 0 0 1 0 8 M20 21v-2a4 4 0 0 0-3-4',

        'Laboratories' => 'M9 3h6 M10 3v7L4 19a1 1 0 0 0 1 2h14a1 1 0 0 0 1-2l-6-9V3 M7 15h10',
    ];

    $initials = collect(
        preg_split('/\s+/u', trim($u->name))
    )->filter()
      ->take(2)
      ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
      ->implode('');
@endphp

{{-- Mobile backdrop --}}
<div
    x-show="sidebar"
    x-cloak
    @click="closeMenu()"
    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
    aria-hidden="true">
</div>

{{-- Main sidebar --}}
<aside
    :data-open="sidebar.toString()"
    class="workspace-sidebar invisible fixed inset-y-0 left-0 z-50 flex max-w-full -translate-x-full flex-col bg-[#111e34] text-white motion-safe:transition-transform motion-safe:duration-200 data-[open=true]:visible data-[open=true]:translate-x-0 lg:visible lg:sticky lg:translate-x-0 print:hidden">

    {{-- Application branding --}}
    <div class="flex min-h-20 items-center justify-between gap-2 border-b border-white/10 px-4">

        <a
            href="{{ route('dashboard') }}"
            class="flex min-h-11 min-w-0 items-center gap-3 rounded-lg">

            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-700 text-white"
                aria-hidden="true">

                <svg
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round">

                    <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                    <path d="M3 8v8l9 5 9-5V8"/>
                    <path d="M12 13v8"/>

                </svg>

            </span>

            <span class="min-w-0">
                <span class="block text-base font-bold tracking-tight">
                    Equip Me Maybe
                </span>

                <span class="block text-xs text-slate-300">
                    Equipment Management
                </span>
            </span>

        </a>

        {{-- Close mobile navigation --}}
        <button
            type="button"
            x-ref="closeMenu"
            @click="closeMenu()"
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-500 hover:bg-white/10 lg:hidden"
            aria-label="Close navigation">

            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true">

                <path d="M6 6l12 12M18 6 6 18"/>

            </svg>

        </button>

    </div>

    {{-- Sidebar content --}}
    <div
        id="workspace-navigation"
        class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-y-contain p-4">

        {{-- Navigation heading --}}
        <p
            class="mb-3 px-3 text-xs font-semibold uppercase tracking-widest text-slate-300"
            aria-hidden="true">

            Workspace

        </p>

        {{-- Role-based navigation --}}
        <nav
            aria-label="Main navigation"
            class="space-y-1.5 pb-6">

            @foreach ($links as [$label, $url, $pattern])

                @php
                    $active = request()->routeIs(
                        ...explode('|', $pattern)
                    );
                @endphp

                <a
                    href="{{ $url }}"
                    @if ($active)
                        aria-current="page"
                    @endif
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold {{ $active ? 'bg-blue-700 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">

                    {{-- Decorative navigation icon --}}
                    <svg
                        class="h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        focusable="false">

                        <path d="{{ $icons[$label] ?? 'M4 4h16v16H4z' }}"/>

                    </svg>

                    <span class="min-w-0 flex-1 break-words">
                        {{ $label }}
                    </span>

                    @if ($active)
                        <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-200"
                            aria-hidden="true">
                        </span>
                    @endif

                </a>

            @endforeach

        </nav>

        {{-- Account section --}}
        <div class="mt-auto border-t border-white/15 pt-4">

            <p
                class="mb-3 px-3 text-xs font-semibold uppercase tracking-widest text-slate-300"
                aria-hidden="true">

                Account

            </p>

            {{-- User profile card --}}
            <div class="mb-3 flex items-center gap-3 rounded-xl bg-white/5 p-3">

                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-700 text-sm font-bold text-white"
                    aria-hidden="true">

                    {{ $initials }}

                </div>

                <div class="min-w-0">

                    <p class="break-words text-sm font-semibold text-white">
                        {{ $u->name }}
                    </p>

                    <p class="mt-0.5 text-xs text-slate-300">
                        {{ $u->role->label() }}
                    </p>

                </div>

            </div>

            {{-- Profile link --}}
            <a
                href="{{ route('profile.edit') }}"
                @if (request()->routeIs('profile.*'))
                    aria-current="page"
                @endif
                class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm hover:bg-white/10 {{ request()->routeIs('profile.*') ? 'bg-blue-700 text-white' : 'text-slate-200' }}">

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true">

                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>

                </svg>

                <span>Profile</span>

            </a>

            {{-- Logout --}}
            <form
                method="POST"
                action="{{ route('logout') }}">

                @csrf

                <button
                    type="submit"
                    class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm text-slate-200 hover:bg-red-500/10 hover:text-red-300">

                    <svg
                        class="h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">

                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>

                    </svg>

                    <span>Log out</span>

                </button>

            </form>

        </div>

    </div>

</aside>
