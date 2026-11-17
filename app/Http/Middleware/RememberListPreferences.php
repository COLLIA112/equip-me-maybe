<?php

namespace App\Http\Middleware;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RememberListPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $rules = $this->rules($name);
        $user = $request->user();
        if (! $request->isMethod('GET') || ! $user || ! $rules) {
            return $next($request);
        }

        $preferences = $user->preferences ?? [];
        $remember = $preferences['remember_filters'] ?? true;
        if ($request->query('clear') === '1') {
            unset($preferences['lists'][$name]);
            $user->forceFill(['preferences' => $preferences])->save();
            $request->session()->forget('list_back.'.$user->id.'.'.$name);

            return redirect($request->url());
        }

        $query = $request->query->all();
        $explicit = array_intersect_key($query, $rules);
        $values = $explicit;
        if ($explicit === [] && $remember) {
            $stored = $preferences['lists'][$name] ?? [];
            $values = is_array($stored) ? $stored : [];
        }
        $normalized = [];
        foreach ($rules as $key => $rule) {
            $value = $values[$key] ?? null;
            if (! is_scalar($value) || is_bool($value) || $value === '') {
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value !== '' && Validator::make([$key => $value], [$key => $rule])->passes()) {
                $normalized[$key] = $value;
            }
        }
        if ($name === 'department.index' && ($normalized['period'] ?? '') === 'custom') {
            $valid = isset($normalized['from'], $normalized['to'])
                && $normalized['from'] <= $normalized['to']
                && Carbon::parse($normalized['from'])->diffInDays(Carbon::parse($normalized['to'])) < 366;
            if (! $valid) {
                $normalized = ['period' => 'month'];
            }
        }
        $page = $query['page'] ?? null;
        if (is_scalar($page) && filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            $normalized['page'] = (int) $page;
        }
        $request->query->replace($normalized);

        $response = $next($request);
        if ($response->isSuccessful()) {
            $saved = array_diff_key($normalized, ['page' => true]);
            if ($remember && ! ($name === 'equipment.index' && isset($normalized['model']))) {
                $preferences = $user->fresh()->preferences ?? [];
                $preferences['lists'][$name] = $saved;
                $user->forceFill(['preferences' => $preferences])->save();
            }
            // Only store URLs produced from the local route and normalized filters.
            $request->session()->put('list_back.'.$user->id.'.'.$name,
                $request->getPathInfo().($normalized ? '?'.http_build_query($normalized) : ''));
        }

        return $response;
    }

    private function rules(?string $name): array
    {
        $direction = ['nullable', Rule::in(['asc', 'desc'])];

        return match ($name) {
            'department.index' => [
                'period' => ['nullable', Rule::in(['week', 'month', 'semester', 'custom'])],
                'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            ],
            'equipment.index' => [
                'q' => ['nullable', 'string', 'max:150'],
                'model' => ['nullable', 'string', 'max:255'],
                'department_id' => ['nullable', 'integer', 'exists:departments,id'],
                'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
                'status' => ['nullable', Rule::enum(EquipmentStatus::class)],
                'tag' => ['nullable', 'integer', 'exists:tags,id'],
                'sort' => ['nullable', Rule::in(['name', 'serial_number', 'status', 'created_at'])],
                'direction' => $direction,
            ],
            'reservations.index', 'reservations.queue' => [
                'q' => ['nullable', 'string', 'max:150'],
                'status' => ['nullable', Rule::enum(ReservationStatus::class)],
                'sort' => ['nullable', Rule::in(['starts_at', 'ends_at', 'status', 'equipment', 'borrower'])],
                'direction' => $direction,
                'overdue' => ['nullable', Rule::in(['1'])],
            ],
            'maintenance.index' => [
                'status' => ['nullable', Rule::in(['open', 'resolved'])],
                'sort' => ['nullable', Rule::in(['created_at', 'issue', 'resolved_at'])],
                'direction' => $direction,
            ],
            'documents.index', 'documents.checklist' => [
                'required' => ['nullable', Rule::in(['required', 'optional', 'all'])],
                'acknowledged' => ['nullable', Rule::in(['yes', 'no'])],
                'sort' => ['nullable', Rule::in(['title', 'is_required', 'created_at'])],
                'direction' => $direction,
            ],
            default => [],
        };
    }
}
