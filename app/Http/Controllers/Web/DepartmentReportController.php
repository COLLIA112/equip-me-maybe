<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Equipment;
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

        return view('department.index', compact('department', 'mostReserved', 'underRepair'));
    }

    /** Breakdown History per equipment. */
    public function breakdowns(Equipment $equipment)
    {
        $this->authorize('viewBreakdowns', $equipment);

        $logs = $equipment->maintenanceLogs()
            ->with(['reporter', 'reservation.user'])
            ->latest()
            ->paginate(15);

        return view('department.breakdowns', compact('equipment', 'logs'));
    }
}
