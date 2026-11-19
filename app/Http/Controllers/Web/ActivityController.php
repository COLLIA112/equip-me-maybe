<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        abort_if($request->user()->hasRole(Role::SecurityAuditor), 403);
        $filters = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'min:1'],
            'reservation_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $activities = ActivityLog::forViewer($request->user())->with(['actor', 'equipment', 'reservation'])
            ->when($filters['equipment_id'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['reservation_id'] ?? null, fn ($query, $id) => $query->where('reservation_id', $id))
            ->latest('id')->paginate(20)->withQueryString();

        return view('activities.index', compact('activities'));
    }
}
