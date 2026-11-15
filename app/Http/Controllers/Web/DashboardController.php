<?php

namespace App\Http\Controllers\Web;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasRole(Role::SecurityAuditor), 403);
        if ($user->hasRole(Role::DepartmentHead)) {
            return redirect()->route('department.index');
        }

        $stats = [];
        $recent = $upcoming = $active = $recentMaintenance = $pendingDocuments = collect();
        $documentTotal = $documentAcknowledged = 0;

        if ($user->isBorrower()) {
            // All borrower counts are calculated together; lists load the equipment up front.
            $counts = User::whereKey($user->id)->withCount([
                'reservations as pending_count' => fn ($query) => $query->where('status', ReservationStatus::Pending),
                'reservations as upcoming_count' => fn ($query) => $query->where('status', ReservationStatus::Approved)->where('ends_at', '>', now()),
                'reservations as checked_out_count' => fn ($query) => $query->checkedOut(),
                'reservations as class_count' => fn ($query) => $query->where('is_class_session', true)
                    ->whereIn('status', [ReservationStatus::Pending, ...ReservationStatus::blocking()]),
            ])->firstOrFail();
            $stats = [
                'Pending requests' => $counts->pending_count,
                'Upcoming reservations' => $counts->upcoming_count,
                'Active checkouts' => $counts->checked_out_count,
                'Available equipment' => Equipment::where('status', EquipmentStatus::Available)->count(),
            ];
            if ($user->hasRole(Role::Faculty)) {
                $stats['Open class sessions'] = $counts->class_count;
            }

            $openEquipment = $user->reservations()->whereIn('status', [ReservationStatus::Pending, ...ReservationStatus::blocking()])->select('equipment_id');
            $required = Document::where('is_required', true)->whereIn('equipment_id', $openEquipment);
            $documentTotal = (clone $required)->count();
            $missing = (clone $required)->whereDoesntHave('users', fn ($query) => $query->where('users.id', $user->id)->whereNotNull('document_user.acknowledged_at'));
            $documentAcknowledged = $documentTotal - (clone $missing)->count();
            $pendingDocuments = $missing->with('equipment')->orderBy('title')->limit(5)->get();
            $stats['Pending documents'] = $documentTotal - $documentAcknowledged;

            $recent = $user->reservations()->with('equipment.lab')->latest('updated_at')->limit(6)->get();
            $upcoming = $user->reservations()->with('equipment.lab')->where('status', ReservationStatus::Approved)->where('ends_at', '>', now())->orderBy('starts_at')->limit(5)->get();
            $active = $user->reservations()->with('equipment.lab')->checkedOut()->orderBy('ends_at')->limit(5)->get();
        }

        if ($user->hasRole(Role::LabManager)) {
            $counts = Lab::whereKey($user->lab_id)->withCount([
                'equipment',
                'equipment as repair_count' => fn ($query) => $query->where('status', EquipmentStatus::UnderRepair),
                'reservations as pending_count' => fn ($query) => $query->where('reservations.status', ReservationStatus::Pending),
                'reservations as checked_out_count' => fn ($query) => $query->where('reservations.status', ReservationStatus::CheckedOut),
                'reservations as due_today_count' => fn ($query) => $query->where('reservations.status', ReservationStatus::CheckedOut)->whereDate('ends_at', today()),
                'reservations as overdue_count' => fn ($query) => $query->where('reservations.status', ReservationStatus::CheckedOut)->where('ends_at', '<', now()),
            ])->first();
            $stats = [
                'Equipment in my lab' => $counts?->equipment_count ?? 0,
                'Needs approval' => $counts?->pending_count ?? 0,
                'Due today' => $counts?->due_today_count ?? 0,
                'Overdue returns' => $counts?->overdue_count ?? 0,
                'Under repair' => $counts?->repair_count ?? 0,
                'Active checkouts' => $counts?->checked_out_count ?? 0,
            ];
            $reservations = Reservation::whereHas('equipment', fn ($query) => $query->where('lab_id', $user->lab_id));
            $recent = $reservations->with(['equipment.lab', 'user'])->latest('updated_at')->limit(6)->get();
            $recentMaintenance = MaintenanceLog::whereHas('equipment', fn ($query) => $query->where('lab_id', $user->lab_id))
                ->with('equipment')->latest()->limit(4)->get();
        }

        $role = $user->role;

        return view('dashboard', compact('role', 'stats', 'recent', 'upcoming', 'active', 'recentMaintenance', 'pendingDocuments', 'documentTotal', 'documentAcknowledged'));
    }
}
