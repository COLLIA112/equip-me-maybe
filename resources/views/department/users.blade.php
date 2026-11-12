<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Users and roles</h1></x-slot>
    <x-card class="overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Lab (managers)</th><th class="px-4 py-3"></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr class="border-t">
                    <td class="px-4 py-2 font-medium">
                        {{ $u->name }}
                        <form id="user-{{ $u->id }}" method="POST" action="{{ route('department.users.update', $u) }}">@csrf @method('PATCH')</form>
                    </td>
                    <td class="px-4 py-2 text-slate-500">{{ $u->email }}</td>
                    <td class="px-4 py-2">
                        <select form="user-{{ $u->id }}" name="role" class="rounded-md border-slate-300 text-sm">
                            @foreach ($roles as $r)<option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->label() }}</option>@endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <select form="user-{{ $u->id }}" name="lab_id" class="rounded-md border-slate-300 text-sm">
                            <option value="">None</option>
                            @foreach ($labs as $lab)<option value="{{ $lab->id }}" @selected($u->lab_id === $lab->id)>{{ $lab->name }}</option>@endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2 text-right"><button form="user-{{ $u->id }}" class="rounded-md bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-900">Save</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
