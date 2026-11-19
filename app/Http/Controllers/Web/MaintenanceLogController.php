<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceFilterRequest;
use App\Http\Requests\StoreMaintenanceLogRequest;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use App\Services\ActivityRecorder;
use App\Services\AvailabilityNotifier;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

            $log = $equipment->maintenanceLogs()->create([
                'reservation_id' => $active?->id,
                'reported_by' => $request->user()->id,
                'issue' => $request->input('issue'),
                'repair_cost' => $request->validated('repair_cost'),
            ]);

            $equipment->update(['status' => EquipmentStatus::UnderRepair]);
            ActivityRecorder::record('maintenance.reported', $request->user(), $equipment, $active, ['maintenance_log_id' => $log->id, 'repair_cost' => $log->repair_cost]);
            $equipment->reservations()->where('status', ReservationStatus::Approved)
                ->where('ends_at', '>', now())->with('user')->eachById(function ($booking) use ($log, $equipment) {
                    if (! $booking->user?->isBorrower()) {
                        return;
                    }
                    NotificationDispatcher::once($booking->user, "repair:{$log->id}:{$booking->id}", [
                        'kind' => 'maintenance', 'title' => 'Reserved equipment under repair',
                        'message' => $equipment->name.' cannot be handed over until the repair is resolved.',
                        'url' => route('reservations.show', $booking, false),
                        'reservation_id' => $booking->id, 'equipment_id' => $equipment->id,
                    ]);
                });
            AvailabilityNotifier::notify($equipment);
        });

        return back()->with('status', 'Breakdown logged. Item is now under repair.');
    }

    public function resolve(Request $request, MaintenanceLog $log): RedirectResponse
    {
        $equipment = $log->equipment;
        $this->authorize('update', $equipment);

        DB::transaction(function () use ($log, $equipment, $request) {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            $log->refresh();
            if ($log->resolved_at !== null) {
                return;
            }

            $log->update(['resolved_at' => now()]);
            ActivityRecorder::record('maintenance.resolved', $request->user(), $equipment, null, ['maintenance_log_id' => $log->id, 'repair_cost' => $log->repair_cost]);

            if (! $equipment->maintenanceLogs()->whereNull('resolved_at')->exists()) {
                $equipment->update([
                    'status' => $equipment->reservations()->checkedOut()->exists()
                        ? EquipmentStatus::Reserved
                        : EquipmentStatus::Available,
                ]);
                AvailabilityNotifier::notify($equipment);
            }
        });

        return back()->with('status', 'Marked as resolved.');
    }
}
