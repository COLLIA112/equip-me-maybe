<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    /** User and Role Management (Department Head). */
    public function index(Request $request)
    {
        $deptId = $request->user()->department_id;

        $users = User::query()
            ->with('lab')
            ->where(fn ($q) => $q->where('department_id', $deptId)->orWhereNull('department_id'))
            ->where('role', '!=', Role::DepartmentHead->value)
            ->orderBy('name')
            ->paginate(20);

        return view('department.users', [
            'users' => $users,
            'labs' => Lab::where('department_id', $deptId)->orderBy('name')->get(),
            'roles' => Role::assignable(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $head = $request->user();

        // Heads can only touch users in their department (or unassigned newcomers), never other heads.
        abort_if($user->hasRole(Role::DepartmentHead), 403);
        abort_unless($user->department_id === null || $user->department_id === $head->department_id, 403);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::assignable()))],
            'lab_id' => [
                'nullable',
                Rule::exists('labs', 'id')->where('department_id', $head->department_id),
                Rule::requiredIf($request->input('role') === Role::LabManager->value),
            ],
        ]);

        $user = DB::transaction(function () use ($user, $head, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->hasRole(Role::DepartmentHead), 403);
            abort_unless($user->department_id === null || $user->department_id === $head->department_id, 403);

            $before = $user->role;
            $user->role = Role::from($data['role']);
            $user->department_id = $head->department_id;
            $user->lab_id = $user->role === Role::LabManager ? $data['lab_id'] : null;
            $user->save();

            if ($user->wasChanged('role')) {
                $user->tokens()->delete();
            }
            if ($user->wasChanged(['role', 'department_id', 'lab_id'])) {
                ActivityRecorder::record('user.role-updated', $head, details: [
                    'user_id' => $user->id, 'department_id' => $user->department_id,
                    'lab_id' => $user->lab_id, 'from_role' => $before->value, 'to_role' => $user->role->value,
                ]);
            }

            return $user;
        }, 3);

        return back()->with('status', "{$user->name} is now {$user->role->label()}.");
    }
}
