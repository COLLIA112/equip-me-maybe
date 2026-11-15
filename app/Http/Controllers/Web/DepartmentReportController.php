<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;

class DepartmentReportController extends Controller
{
    /** Department Dashboard: every lab, usage and items under repair, with no N+1 queries. */
    public function index(Request $request)
    {
        $department = Department::query()
            ->with(['labs' => fn ($q) => $q
                ->withCount([
                    'equipment',
                    'reservations',
                    'equipment as under_repair_count' => fn ($e) => $e->where('status', EquipmentStatus::UnderRepair),
                ])
                ->orderBy('name')])
            ->withCount('equipment') // Has Many Through: Department -> Lab -> Equipment
            ->findOrFail($request->user()->department_id);

        $mostReserved = $department->equipment()
            ->with('lab')
            ->withCount(['reservations', 'maintenanceLogs'])
            ->orderByDesc('reservations_count')
            ->take(10)
            ->get();

        $underRepair = $department->equipment()
            ->with('lab')
            ->where('equipment.status', EquipmentStatus::UnderRepair)
            ->get();

        $recentMaintenance = MaintenanceLog::query()
            ->whereHas('equipment.lab', fn ($lab) => $lab->where('department_id', $department->id))
            ->with('equipment.lab')
            ->latest()
            ->limit(5)
            ->get();

        $reservationTotal = $department->labs->sum('reservations_count');
        $breakdownTotal = MaintenanceLog::whereHas('equipment.lab', fn ($query) => $query->where('department_id', $department->id))->count();

        return view('department.index', compact('department', 'mostReserved', 'underRepair', 'recentMaintenance', 'reservationTotal', 'breakdownTotal'));
    }

    /** Breakdown History per equipment. */
    public function breakdowns(Equipment $equipment)
    {
        $this->authorize('viewBreakdowns', $equipment);

        $logs = $equipment->maintenanceLogs()
            ->with(['reporter', 'reservation.user'])
            ->latest()
            ->paginate(15)->withQueryString();

        return view('department.breakdowns', compact('equipment', 'logs'));
    }
}
