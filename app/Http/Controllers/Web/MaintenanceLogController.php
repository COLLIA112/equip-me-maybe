<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceLogRequest;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('create', Equipment::class); // manager or head

        $user = $request->user();

        $logs = MaintenanceLog::query()
            ->with(['equipment.lab', 'reporter', 'reservation.user'])
            ->whereHas('equipment.lab', function ($q) use ($user) {
                $user->hasRole(Role::LabManager)
                    ? $q->whereKey($user->lab_id)
                    : $q->where('department_id', $user->department_id);
            })
            ->latest()
            ->paginate(15);

        $equipment = Equipment::where('lab_id', $user->lab_id)->orderBy('name')->get(['id', 'name']);

        return view('maintenance.index', compact('logs', 'equipment'));
    }

    /** Report a breakdown: flips the item to under_repair and blocks new reservations. */
    public function store(StoreMaintenanceLogRequest $request): RedirectResponse
    {
        $equipment = Equipment::findOrFail($request->integer('equipment_id'));
        $this->authorize('update', $equipment);

        DB::transaction(function () use ($request, $equipment) {
            // The borrower of record: whoever has it checked out right now.
            $active = $equipment->currentCheckout();

            $equipment->maintenanceLogs()->create([
                'reservation_id' => $active?->id,
                'reported_by' => $request->user()->id,
                'issue' => $request->input('issue'),
            ]);

            $equipment->update(['status' => EquipmentStatus::UnderRepair]);
        });

        return back()->with('status', 'Breakdown logged. Item is now under repair.');
    }

    public function resolve(MaintenanceLog $log): RedirectResponse
    {
        $equipment = $log->equipment;
        $this->authorize('update', $equipment);

        DB::transaction(function () use ($log, $equipment) {
            $log->update(['resolved_at' => now()]);

            // Only reopen the item when no other breakdown is still open.
            if (! $equipment->maintenanceLogs()->whereNull('resolved_at')->exists()) {
                $equipment->update(['status' => EquipmentStatus::Available]);
            }
        });

        return back()->with('status', 'Marked as resolved.');
    }
}
