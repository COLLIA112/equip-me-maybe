<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class DepartmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The department-report Gate protects each route.
    }

    public function rules(): array
    {
        return [];
    }

    /** Inclusive calendar dates at the boundary; SQL uses the exclusive next midnight. */
    public function range(): array
    {
        $period = $this->query('period', 'month');
        $period = is_string($period) && in_array($period, ['week', 'month', 'semester', 'custom'], true) ? $period : 'month';
        $start = now()->startOfMonth();
        $end = now()->endOfMonth()->startOfDay();

        if ($period === 'week') {
            $start = now()->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addDays(6);
        } elseif ($period === 'semester') {
            $start = now()->setMonth(now()->month <= 6 ? 1 : 7)->startOfMonth();
            $end = $start->copy()->addMonths(6)->subDay();
        } elseif ($period === 'custom') {
            $from = $this->query('from');
            $to = $this->query('to');
            $valid = is_string($from) && is_string($to)
                && Validator::make(['from' => $from, 'to' => $to], [
                    'from' => ['required', 'date_format:Y-m-d'],
                    'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                ])->passes();
            if ($valid) {
                $candidateStart = Carbon::createFromFormat('!Y-m-d', $from);
                $candidateEnd = Carbon::createFromFormat('!Y-m-d', $to);
                $valid = $candidateStart->diffInDays($candidateEnd) < 366;
            }
            if ($valid) {
                $start = $candidateStart;
                $end = $candidateEnd;
            } else {
                $period = 'month';
            }
        }

        return [
            'period' => $period,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'start' => $start->startOfDay(),
            'end' => $end->copy()->addDay()->startOfDay(),
        ];
    }
}
