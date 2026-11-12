<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\User;

class DocumentPolicy
{
    /** Upload: manager of the equipment's own lab. Called as authorize('create', [Document::class, $equipment]). */
    public function create(User $user, Equipment $equipment): bool
    {
        return $user->managesLab($equipment->lab_id);
    }

    /** Download: borrowers, the lab's manager, and the department's head. Never security auditors. */
    public function download(User $user, Document $document): bool
    {
        if ($user->isBorrower()) {
            return true;
        }

        $document->loadMissing('equipment.lab');
        $equipment = $document->equipment;

        return $user->managesLab($equipment->lab_id)
            || $user->headsDepartment($equipment->lab->department_id);
    }

    public function delete(User $user, Document $document): bool
    {
        $document->loadMissing('equipment');

        return $user->managesLab($document->equipment->lab_id);
    }
}
