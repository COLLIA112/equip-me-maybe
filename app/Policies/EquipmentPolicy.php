<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(Role::SecurityAuditor); // auditors use the API only
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::LabManager, Role::DepartmentHead);
    }

    /** A lab manager only edits equipment in their own lab; a head, anything in their department. */
    public function update(User $user, Equipment $equipment): bool
    {
        if ($user->managesLab($equipment->lab_id)) {
            return true;
        }

        return $user->headsDepartment($equipment->loadMissing('lab')->lab->department_id);
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $this->update($user, $equipment);
    }

    public function viewBreakdowns(User $user, Equipment $equipment): bool
    {
        return $user->headsDepartment($equipment->loadMissing('lab')->lab->department_id)
            || $user->managesLab($equipment->lab_id);
    }
}
