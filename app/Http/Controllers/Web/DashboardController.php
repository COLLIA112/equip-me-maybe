<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $stats = [];
        $recent = collect();
        $recentMaintenance = collect();

        if ($user->hasRole(Role::DepartmentHead)) {
            return redirect()->route('department.index');
        }

        if ($user->isBorrower()) {
            $stats = [
                'Pending requests' => $user->reservations()->where('status', ReservationStatus::Pending)->count(),
                'Approved bookings' => $user->reservations()->where('status', ReservationStatus::Approved)->where('ends_at', '>', now())->count(),
                'Checked out' => $user->reservations()->checkedOut()->count(),
            ];

            if ($user->hasRole(Role::Faculty)) {
                $stats['Open class sessions'] = $user->reservations()->where('is_class_session', true)
                    ->whereIn('status', [ReservationStatus::Pending, ...ReservationStatus::blocking()])->count();
            }

            $recent = $user->reservations()->with('equipment')->latest('updated_at')->limit(6)->get();
        }

        if ($user->hasRole(Role::LabManager)) {
            $equipment = Equipment::where('lab_id', $user->lab_id);
            $reservations = Reservation::whereHas('equipment', fn ($query) => $query->where('lab_id', $user->lab_id));
            $stats = [
                'Equipment in my lab' => (clone $equipment)->count(),
                'Under repair' => (clone $equipment)->where('status', EquipmentStatus::UnderRepair)->count(),
                'Pending requests' => (clone $reservations)->where('status', ReservationStatus::Pending)->count(),
                'Checked out' => (clone $reservations)->checkedOut()->count(),
            ];
            $recent = $reservations->with(['equipment', 'user'])->latest('updated_at')->limit(6)->get();
            $recentMaintenance = MaintenanceLog::whereHas('equipment', fn ($query) => $query->where('lab_id', $user->lab_id))
                ->with('equipment')
                ->latest()
                ->limit(4)
                ->get();
        }

        $role = $user->role;

        return view('dashboard', compact('role', 'stats', 'recent', 'recentMaintenance'));
    }
}
