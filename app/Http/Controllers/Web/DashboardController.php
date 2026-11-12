<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Reservation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** One dashboard, content changes by role. */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $stats = [];

        if ($user->isBorrower()) {
            $stats['upcoming'] = $user->reservations()
                ->with('equipment')
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::CheckedOut])
                ->orderBy('starts_at')
                ->take(5)
                ->get();
        }

        if ($user->hasRole(Role::LabManager)) {
            $labEquipment = Equipment::where('lab_id', $user->lab_id);
            $stats['equipment_total'] = (clone $labEquipment)->count();
            $stats['under_repair'] = (clone $labEquipment)->where('status', EquipmentStatus::UnderRepair)->count();
            $stats['pending_requests'] = Reservation::where('status', ReservationStatus::Pending)
                ->whereHas('equipment', fn ($q) => $q->where('lab_id', $user->lab_id))
                ->count();
        }

        if ($user->hasRole(Role::DepartmentHead)) {
            return redirect()->route('department.index');
        }

        return view('dashboard', ['role' => $user->role, 'stats' => $stats]);
    }
}
