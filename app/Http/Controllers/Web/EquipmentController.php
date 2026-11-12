<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    /** Catalog with search and filters by department, lab, status and tag. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Equipment::class);

        $equipment = Equipment::query()
            ->with(['lab.department', 'tags'])
            ->filter($request->only(['q', 'department_id', 'lab_id', 'status', 'tag']))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('equipment.index', [
            'equipment' => $equipment,
            'departments' => Department::orderBy('name')->get(),
            'labs' => Lab::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => $request->only(['q', 'department_id', 'lab_id', 'status', 'tag']),
        ]);
    }

    public function show(Request $request, Equipment $equipment)
    {
        $this->authorize('view', $equipment);

        $equipment->load(['lab.department', 'tags', 'documents']);

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

        return view('equipment.show', compact('equipment', 'booked', 'progress'));
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
        $labId = $user->hasRole(\App\Enums\Role::LabManager) ? $user->lab_id : $request->integer('lab_id');

        // A head may only add to labs of their own department.
        abort_unless(Lab::whereKey($labId)->when(
            $user->hasRole(\App\Enums\Role::DepartmentHead),
            fn ($q) => $q->where('department_id', $user->department_id)
        )->exists(), 403);

        $equipment = Equipment::create([
            'lab_id' => $labId,
            'name' => $request->string('name'),
            'serial_number' => $request->string('serial_number'),
            'photo_path' => $request->file('photo')?->store('equipment', 'public'),
        ]);
        $equipment->tags()->sync($request->input('tags', []));

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

        return redirect()->route('equipment.show', $equipment)->with('status', 'Equipment updated.');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        $this->authorize('delete', $equipment);

        $equipment->delete();

        return redirect()->route('equipment.index')->with('status', 'Equipment deleted.');
    }

    private function formData(Request $request): array
    {
        $user = $request->user();

        return [
            'tags' => Tag::orderBy('name')->get(),
            'labs' => Lab::query()
                ->when($user->hasRole(\App\Enums\Role::DepartmentHead), fn ($q) => $q->where('department_id', $user->department_id))
                ->when($user->hasRole(\App\Enums\Role::LabManager), fn ($q) => $q->whereKey($user->lab_id))
                ->orderBy('name')->get(),
        ];
    }
}
