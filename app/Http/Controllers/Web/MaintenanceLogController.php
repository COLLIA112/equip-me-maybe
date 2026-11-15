<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceFilterRequest;
use App\Http\Requests\StoreMaintenanceLogRequest;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MaintenanceLogController extends Controller
{
    public function index(MaintenanceFilterRequest $request)
    {
        $this->authorize('create', Equipment::class); // manager or head

        $user = $request->user();

        $visibleLabs = fn ($query) => $user->hasRole(Role::LabManager)
            ? $query->whereKey($user->lab_id)
            : $query->where('department_id', $user->department_id);

        $filters = $request->filters();
        $activeFilters = isset($filters['status']);

        $logs = MaintenanceLog::query()
            ->with(['equipment.lab', 'reporter', 'reservation.user'])
            ->whereHas('equipment.lab', $visibleLabs)
            ->when(($filters['status'] ?? null) === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when(($filters['status'] ?? null) === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $equipment = Equipment::whereHas('lab', $visibleLabs)->orderBy('name')->get(['id', 'name']);

        return view('maintenance.index', compact('logs', 'equipment', 'filters', 'activeFilters'));
    }

    /** Report a breakdown: flips the item to under_repair and blocks new reservations. */
    public function store(StoreMaintenanceLogRequest $request): RedirectResponse
    {
        $equipment = Equipment::findOrFail($request->integer('equipment_id'));
        $this->authorize('update', $equipment);

        DB::transaction(function () use ($request, $equipment) {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();

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
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            $log->refresh();
            if ($log->resolved_at !== null) {
                return;
            }

            $log->update(['resolved_at' => now()]);

            if (! $equipment->maintenanceLogs()->whereNull('resolved_at')->exists()) {
                $equipment->update([
                    'status' => $equipment->reservations()->checkedOut()->exists()
                        ? EquipmentStatus::Reserved
                        : EquipmentStatus::Available,
                ]);
            }
        });

        return back()->with('status', 'Marked as resolved.');
    }
}
