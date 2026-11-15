<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClosureRequest;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\LabClosure;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LabClosureController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage_equipment');
        $user = $request->user();
        $labs = Lab::query()->when($user->hasRole(Role::LabManager), fn ($q) => $q->whereKey($user->lab_id),
            fn ($q) => $q->where('department_id', $user->department_id))->orderBy('name')->get();
        $closures = LabClosure::whereIn('lab_id', $labs->pluck('id'))->with(['lab', 'creator'])
            ->orderByDesc('starts_at')->paginate(15)->withQueryString();

        return view('lab-closures.index', compact('labs', 'closures'));
    }

    public function store(ClosureRequest $request)
    {
        DB::transaction(function () use ($request) {
            $lab = Lab::whereKey($request->validated('lab_id'))->lockForUpdate()->firstOrFail();
            $this->assertNoApprovedBookings($lab, $request->validated());
            $closure = LabClosure::create($request->validated() + ['created_by' => $request->user()->id]);
            $this->recordChange('lab-closure.created', $request, $lab, $closure);
        });

        return back()->with('status', 'Laboratory closure scheduled. Overlapping requests and approvals will be blocked.');
    }

    public function update(ClosureRequest $request, LabClosure $labClosure)
    {
        DB::transaction(function () use ($request, $labClosure) {
            Lab::whereIn('id', [$labClosure->lab_id, $request->validated('lab_id')])->orderBy('id')->lockForUpdate()->get();
            $lab = Lab::findOrFail($request->validated('lab_id'));
            $this->assertNoApprovedBookings($lab, $request->validated());
            $labClosure->update($request->validated());
            $this->recordChange('lab-closure.updated', $request, $lab, $labClosure);
        });

        return back()->with('status', 'Laboratory closure updated.');
    }

    private function assertNoApprovedBookings(Lab $lab, array $data): void
    {
        if (Reservation::blocking()->whereHas('equipment', fn ($equipment) => $equipment->where('lab_id', $lab->id))
            ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'This closure overlaps an approved booking or checkout. Resolve those bookings before scheduling a closure.']);
        }
    }

    private function recordChange(string $event, Request $request, Lab $lab, LabClosure $closure): void
    {
        ActivityLog::create(['event' => $event, 'actor_id' => $request->user()->id, 'details' => [
            'lab_id' => $lab->id, 'department_id' => $lab->department_id,
            'reason' => $closure->reason, 'starts_at' => $closure->starts_at->toDateTimeString(),
            'ends_at' => $closure->ends_at->toDateTimeString(),
        ]]);
    }

    public function destroy(Request $request, LabClosure $labClosure)
    {
        Gate::authorize('manage_equipment');
        abort_unless($request->user()->managesLab($labClosure->lab_id)
            || $request->user()->headsDepartment($labClosure->lab()->value('department_id')), 403);
        DB::transaction(function () use ($request, $labClosure) {
            $lab = Lab::whereKey($labClosure->lab_id)->lockForUpdate()->firstOrFail();
            $this->recordChange('lab-closure.deleted', $request, $lab, $labClosure);
            $labClosure->delete();
        });

        return back()->with('status', 'Laboratory closure removed.');
    }
}
