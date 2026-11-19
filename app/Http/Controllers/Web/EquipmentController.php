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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EquipmentController extends Controller
{
    /** Catalog with search and filters by department, lab, status and tag. */
    public function index(CatalogFilterRequest $request)
    {
        $this->authorize('viewAny', Equipment::class);

        $user = $request->user();
        $isHead = $user->hasRole(Role::DepartmentHead);
        $filters = $request->only(['q', 'department_id', 'lab_id', 'status', 'tag']);
        $search = $filters['q'] ?? '';
        $model = $request->input('model');
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        $catalog = Equipment::query()
            ->when($isHead, fn ($query) => $query->whereHas('lab', fn ($lab) => $lab->where('department_id', $user->department_id)))
            ->filter(collect($filters)->except('q')->all());

        if ($model !== null) {
            $modelKey = Str::lower($model);
            $ids = (clone $catalog)
                ->where(fn ($query) => $query->whereLike('name', $model)->orWhereLike('name', $model.'%#%'))
                ->get(['id', 'name'])
                ->filter(fn (Equipment $item) => Str::lower($item->catalogModelName()) === $modelKey)
                ->modelKeys();
            $catalog->whereIntegerInRaw('id', $ids);
        }

        $showUnits = $this->applyCatalogSearch(
            $catalog, $search, $model !== null || isset($filters['lab_id']) || isset($filters['department_id'])
        );
        $catalog->orderBy($sort, $direction)->orderBy('id')->withAvailability();

        $equipment = $showUnits
            ? $catalog->with('lab.department')->paginate(12, page: $request->integer('page', 1))
            : $this->paginateCatalogModels($catalog, $request);
        $equipment->appends($request->except('page'));

        $visibleUnits = $showUnits ? $equipment->getCollection() : $equipment->pluck('unit')->filter();
        $wishlistIds = $user->isBorrower() && $visibleUnits->isNotEmpty()
            ? $user->wishlist()->whereIn('equipment.id', $visibleUnits->pluck('id'))->pluck('equipment.id')->all()
            : [];

        return view('equipment.index', [
            'equipment' => $equipment,
            'model' => $model,
            'showUnits' => $showUnits,
            'modelFilters' => $request->only(['status', 'tag', 'sort', 'direction']),
            'departments' => Department::when($isHead, fn ($query) => $query->whereKey($user->department_id))->orderBy('name')->get(),
            'labs' => Lab::when($isHead, fn ($query) => $query->where('department_id', $user->department_id))->orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'wishlistIds' => $wishlistIds,
        ]);
    }

    /** Numbered units and identifier/location matches use the unit table. */
    private function applyCatalogSearch(Builder $catalog, string $search, bool $showUnits): bool
    {
        if ($search === '') {
            return $showUnits;
        }

        if (preg_match('/^(.*?)\s*#(\d+)$/u', $search, $parts)) {
            $catalog->where(fn ($query) => $query
                ->where(fn ($name) => $name->whereLike('name', '% #'.$parts[2])
                    ->when($parts[1] !== '', fn ($name) => $this->matchCatalogName($name, $parts[1])))
                ->orWhereLike('serial_number', $search)
                ->orWhereLike('qr_code', $search));

            return true;
        }

        $exact = fn ($query) => $this->matchUnitIdentifiers($query, $search);
        if ((clone $catalog)->where($exact)->exists()) {
            $catalog->where($exact);

            return true;
        }

        $pattern = '%'.$search.'%';
        $matchesName = fn ($query) => $this->matchCatalogName($query, $search);
        if (! $showUnits && (clone $catalog)->where($matchesName)->exists()) {
            $catalog->where($matchesName);

            return false;
        }

        $catalog->where(fn ($query) => $this->matchUnitIdentifiers($query, $pattern)->orWhere($matchesName));

        return true;
    }

    private function matchCatalogName(Builder $query, string $search): Builder
    {
        $aliases = config('equipment.catalog_search_aliases', [])[Str::lower($search)] ?? [];

        return $query->where(function (Builder $names) use ($search, $aliases) {
            $names->whereLike('name', '%'.$search.'%');

            foreach ($aliases as $alias) {
                $names->orWhereLike('name', $alias)->orWhereLike('name', $alias.' #%');
            }
        });
    }

    private function matchUnitIdentifiers(Builder $query, string $pattern): Builder
    {
        return $query->whereLike('serial_number', $pattern)
            ->orWhereLike('qr_code', $pattern)
            ->orWhereHas('lab', fn ($lab) => $lab->whereLike('name', $pattern))
            ->orWhereHas('lab.department', fn ($department) => $department->whereLike('name', $pattern));
    }

    private function paginateCatalogModels(Builder $catalog, Request $request): LengthAwarePaginator
    {
        $models = $catalog->get()
            ->groupBy(function (Equipment $item) {
                $model = $item->catalogModelName();

                // Group numbered units by model. Standalone items can share a name.
                return $model === trim($item->name) ? 'unit:'.$item->id : 'model:'.Str::lower($model);
            })
            ->map(fn ($units) => [
                'name' => $units->first()->catalogModelName(),
                'photo_url' => $units->first(fn (Equipment $item) => filled($item->photo_path))?->photo_url,
                'quantity' => $units->count(),
                'available' => $units->filter(fn (Equipment $item) => $item->availabilityGlance()['available'])->count(),
                'unit' => $units->count() === 1 ? $units->first() : null,
            ]);
        $page = $request->integer('page', 1);

        return (new LengthAwarePaginator(
            $models->forPage($page, 12)->values(),
            $models->count(),
            12,
            $page,
            ['path' => $request->url()],
        ));
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
