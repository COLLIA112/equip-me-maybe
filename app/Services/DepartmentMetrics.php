<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Models\Department;
use App\Models\MaintenanceLog;
use App\Models\User;

class DepartmentMetrics
{
    public function collect(User $head, array $range): array
    {
        $start = $range['start'];
        $end = $range['end'];
        $overlap = fn ($query) => $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        $reported = fn ($query) => $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $department = Department::with(['labs' => fn ($query) => $query->withCount([
            'equipment',
            'reservations' => $overlap,
            'equipment as under_repair_count' => fn ($items) => $items->where('status', EquipmentStatus::UnderRepair),
        ])->orderBy('name')])->withCount('equipment')->findOrFail($head->department_id);

        $items = $department->equipment()->with('lab')->withCount([
            'reservations' => $overlap,
            'maintenanceLogs' => $reported,
        ])->with(['reservations' => fn ($query) => $overlap($query)
            ->whereIn('status', [ReservationStatus::Approved, ReservationStatus::CheckedOut, ReservationStatus::Returned])
            ->orderBy('starts_at')])->orderBy('name')->get();

        $hours = $start->diffInSeconds($end) / 3600;
        $usageRows = $items->map(function ($item) use ($start, $end, $hours) {
            // Union each item's clipped booking intervals: duplicate/legacy overlaps never inflate utilization.
            $intervals = $item->reservations->map(fn ($reservation) => [
                max($start->timestamp, $reservation->starts_at->timestamp),
                min($end->timestamp, $reservation->ends_at->timestamp),
            ])->filter(fn ($interval) => $interval[1] > $interval[0])->sortBy(fn ($interval) => $interval[0]);
            $seconds = 0;
            $current = null;
            foreach ($intervals as $interval) {
                if ($current === null) {
                    $current = $interval;
                } elseif ($interval[0] <= $current[1]) {
                    $current[1] = max($current[1], $interval[1]);
                } else {
                    $seconds += $current[1] - $current[0];
                    $current = $interval;
                }
            }
            if ($current !== null) {
                $seconds += $current[1] - $current[0];
            }
            $booked = $seconds / 3600;

            return [
                'equipment_id' => $item->id, 'lab_id' => $item->lab_id, 'lab' => $item->lab->name,
                'name' => $item->name, 'serial_number' => $item->serial_number,
                'reservations' => $item->reservations_count, 'breakdowns' => $item->maintenance_logs_count,
                'booked_hours' => $booked, 'capacity_hours' => $hours,
                'utilization' => $hours > 0 ? min(100, $booked / $hours * 100) : 0,
            ];
        });
        $labUsage = $department->labs->mapWithKeys(function ($lab) use ($usageRows, $hours) {
            $rows = $usageRows->where('lab_id', $lab->id);
            $booked = $rows->sum('booked_hours');
            $capacity = $rows->count() * $hours;

            return [$lab->id => ['booked_hours' => $booked, 'capacity_hours' => $capacity, 'utilization' => $capacity > 0 ? min(100, $booked / $capacity * 100) : 0]];
        });

        $logs = MaintenanceLog::whereHas('equipment.lab', fn ($query) => $query->where('department_id', $department->id))
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->with(['equipment.lab', 'reporter', 'reservation.user'])->latest()->get();
        $monthly = $start->diffInDays($end) > 45;
        $trend = collect();
        $trendCounts = $logs->countBy(fn ($log) => $log->created_at->format($monthly ? 'Y-m' : 'Y-m-d'));
        $cursor = $monthly ? $start->copy()->startOfMonth() : $start->copy();
        while ($cursor->lt($end)) {
            $key = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $trend->push(['label' => $key, 'count' => $trendCounts[$key] ?? 0]);
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return [
            'department' => $department,
            'range' => $range,
            'mostReserved' => $items->sortByDesc('reservations_count')->take(10),
            'underRepair' => $items->filter(fn ($item) => $item->status === EquipmentStatus::UnderRepair),
            'recentMaintenance' => $logs->take(5),
            'reservationTotal' => $items->sum('reservations_count'),
            'breakdownTotal' => $logs->count(),
            'costTotal' => $logs->sum(fn ($log) => (int) round(((float) $log->repair_cost) * 100)) / 100,
            'usageRows' => $usageRows, 'labUsage' => $labUsage, 'breakdownRows' => $logs, 'trend' => $trend,
        ];
    }
}
