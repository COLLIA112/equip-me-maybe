<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\EquipmentCalendarRequest;
use App\Http\Requests\EquipmentRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EquipmentController extends Controller
{
    /** Catalog with search and filters by department, lab, status and tag. */
    public function index(CatalogFilterRequest $request)
    {
        $this->authorize('viewAny', Equipment::class);

        $user = $request->user();
        $isHead = $user->hasRole(Role::DepartmentHead);

        $equipment = Equipment::query()
            ->when($isHead, fn ($query) => $query->whereHas('lab', fn ($lab) => $lab->where('department_id', $user->department_id)))
            ->with(['lab.department', 'tags'])->withAvailability()
            ->filter($request->only(['q', 'department_id', 'lab_id', 'status', 'tag']))
            ->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('equipment.index', [
            'equipment' => $equipment,
            'departments' => Department::when($isHead, fn ($query) => $query->whereKey($user->department_id))->orderBy('name')->get(),
            'labs' => Lab::when($isHead, fn ($query) => $query->where('department_id', $user->department_id))->orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => $request->only(['q', 'department_id', 'lab_id', 'status', 'tag']),
            'sort' => $request->input('sort', 'name'),
            'direction' => $request->input('direction', 'asc'),
            'wishlistIds' => $user->isBorrower() ? $user->wishlist()->pluck('equipment.id')->all() : [],
        ]);
    }

    public function show(EquipmentCalendarRequest $request, Equipment $equipment)
    {
        $this->authorize('view', $equipment);

        $equipment = Equipment::query()->withAvailability()
            ->with(['lab.department', 'tags', 'documents'])->findOrFail($equipment->id);
        $week = Carbon::parse($request->input('week', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $weekBookings = $equipment->reservations()->blocking()
            ->where('starts_at', '<', $week->copy()->addWeek())
            ->where('ends_at', '>', $week)->orderBy('starts_at')
            ->get(['id', 'starts_at', 'ends_at', 'status']);

        // Free slot picker: upcoming booked windows only (no borrower names).
        $booked = $equipment->reservations()
            ->blocking()
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get(['id', 'starts_at', 'ends_at', 'status']);

        // The viewer's own download and acknowledgement state for each document.
        $progress = $request->user()->documents()
            ->whereIn('documents.id', $equipment->documents->pluck('id'))
            ->get()
            ->keyBy('id');

        $wishlisted = $request->user()->isBorrower() && $request->user()->wishlist()->whereKey($equipment->id)->exists();

        return view('equipment.show', compact('equipment', 'booked', 'progress', 'week', 'weekBookings', 'wishlisted'));
    }

    public function qrLabel(Equipment $equipment)
    {
        $this->authorize('update', $equipment);

        return view('equipment.qr-label', compact('equipment'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Equipment::class);

        return view('equipment.create', $this->formData($request));
    }

    public function store(EquipmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Equipment::class);

        $user = $request->user();
        $labId = $user->hasRole(Role::LabManager) ? $user->lab_id : $request->integer('lab_id');

        // A head may only add to labs of their own department.
        abort_unless(Lab::whereKey($labId)->when(
            $user->hasRole(Role::DepartmentHead),
            fn ($q) => $q->where('department_id', $user->department_id)
        )->exists(), 403);

        $equipment = Equipment::create([
            'lab_id' => $labId,
            'name' => $request->string('name'),
            'serial_number' => $request->string('serial_number'),
            'photo_path' => $request->file('photo')?->store('equipment', 'public'),
        ]);
        $equipment->tags()->sync($request->input('tags', []));
        ActivityRecorder::record('equipment.created', $user, $equipment, details: ['name' => $equipment->name, 'serial_number' => $equipment->serial_number]);

        return redirect()->route('equipment.show', $equipment)->with('status', 'Equipment added.');
    }

    public function edit(Request $request, Equipment $equipment)
    {
        $this->authorize('update', $equipment);

        return view('equipment.edit', $this->formData($request) + ['equipment' => $equipment->load('tags')]);
    }

    public function update(EquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('update', $equipment);

        $data = [
            'name' => $request->string('name'),
            'serial_number' => $request->string('serial_number'),
        ];

        if ($photo = $request->file('photo')) {
            if ($equipment->photo_path) {
                Storage::disk('public')->delete($equipment->photo_path);
            }
            $data['photo_path'] = $photo->store('equipment', 'public');
        }

        $equipment->update($data);
        $equipment->tags()->sync($request->input('tags', []));
        ActivityRecorder::record('equipment.updated', $request->user(), $equipment, details: ['name' => $equipment->name, 'serial_number' => $equipment->serial_number]);

        return redirect()->route('equipment.show', $equipment)->with('status', 'Equipment updated.');
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('delete', $equipment);

        DB::transaction(function () use ($equipment, $request) {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            if ($equipment->reservations()->exists() || $equipment->maintenanceLogs()->exists()) {
                throw ValidationException::withMessages([
                    'equipment' => 'Equipment with reservation or maintenance history cannot be deleted. Keep this record for audit history.',
                ]);
            }
            ActivityRecorder::record('equipment.deleted', $request->user(), $equipment, details: ['name' => $equipment->name, 'serial_number' => $equipment->serial_number]);
            $equipment->delete();
        });

        return redirect()->route('equipment.index')->with('status', 'Equipment deleted.');
    }

    private function formData(Request $request): array
    {
        $user = $request->user();

        return [
            'tags' => Tag::orderBy('name')->get(),
            'labs' => Lab::query()
                ->when($user->hasRole(Role::DepartmentHead), fn ($q) => $q->where('department_id', $user->department_id))
                ->when($user->hasRole(Role::LabManager), fn ($q) => $q->whereKey($user->lab_id))
                ->orderBy('name')->get(),
        ];
    }
}
