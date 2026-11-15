<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentReportRequest;
use App\Models\Equipment;
use App\Services\DepartmentMetrics;

class DepartmentReportController extends Controller
{
    public function index(DepartmentReportRequest $request, DepartmentMetrics $metrics)
    {
        return view('department.index', $metrics->collect($request->user(), $request->range()));
    }

    public function export(DepartmentReportRequest $request, DepartmentMetrics $metrics)
    {
        $type = $request->query('type', 'usage');
        $format = $request->query('format', 'csv');
        abort_unless(is_string($type) && in_array($type, ['usage', 'breakdowns'], true)
            && is_string($format) && in_array($format, ['csv', 'print'], true), 422);
        $report = $metrics->collect($request->user(), $request->range());
        if ($format === 'print') {
            return view('department.export', $report + ['type' => $type]);
        }

        return response()->streamDownload(function () use ($report, $type) {
            $stream = fopen('php://output', 'w');
            $write = function (array $fields) use ($stream) {
                $safe = array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value) ? "'".$value : $value;
                }, $fields);
                fputcsv($stream, $safe, ',', '"', '');
            };
            if ($type === 'usage') {
                $write(['Lab', 'Equipment', 'Serial', 'From', 'To', 'Reservation requests', 'Breakdowns', 'Booked hours', '24-hour capacity hours', 'Utilization percent']);
                foreach ($report['usageRows'] as $row) {
                    $write([$row['lab'], $row['name'], $row['serial_number'], $report['range']['from'], $report['range']['to'], $row['reservations'], $row['breakdowns'], number_format($row['booked_hours'], 2, '.', ''), number_format($row['capacity_hours'], 2, '.', ''), number_format($row['utilization'], 2, '.', '')]);
                }
            } else {
                $write(['Lab', 'Equipment', 'Reported', 'Issue', 'Reporter', 'Borrower of record', 'Repair cost', 'Resolved']);
                foreach ($report['breakdownRows'] as $log) {
                    $write([$log->equipment->lab->name, $log->equipment->name, $log->created_at->toDateTimeString(), $log->issue, $log->reporter->name, $log->reservation?->user?->name, $log->repair_cost, $log->resolved_at?->toDateTimeString()]);
                }
            }
            fclose($stream);
        }, 'department-'.$type.'-'.$report['range']['from'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Breakdown history keeps its original full history unless a date range is selected. */
    public function breakdowns(DepartmentReportRequest $request, Equipment $equipment)
    {
        $this->authorize('viewBreakdowns', $equipment);
        $range = $request->range();
        $dateFiltered = $request->hasAny(['period', 'from', 'to']);
        $logs = $equipment->maintenanceLogs()->with(['reporter', 'reservation.user'])
            ->when($dateFiltered, fn ($query) => $query->where('created_at', '>=', $range['start'])->where('created_at', '<', $range['end']))
            ->latest()->paginate(15)->withQueryString();

        return view('department.breakdowns', compact('equipment', 'logs', 'range', 'dateFiltered'));
    }
}
