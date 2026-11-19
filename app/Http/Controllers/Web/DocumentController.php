<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentFilterRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Equipment;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Document Manager screen for one equipment item. */
    public function index(DocumentFilterRequest $request, Equipment $equipment)
    {
        $this->authorize('create', [Document::class, $equipment]);

        $filters = $request->only(['required', 'acknowledged']);
        $documents = $this->filteredDocuments($equipment->documents(), $request)
            ->orderBy($request->input('sort', 'title'), $request->input('direction', 'asc'))
            ->orderBy('documents.id')->paginate(10)->withQueryString();

        return view('documents.index', [
            'equipment' => $equipment, 'documents' => $documents, 'filters' => $filters,
            'sort' => $request->input('sort', 'title'), 'direction' => $request->input('direction', 'asc'),
        ]);
    }

    /** Required Documents checklist for the borrower's open reservations. */
    public function checklist(DocumentFilterRequest $request)
    {
        $this->authorize('reserve_equipment');
        $user = $request->user();

        $equipmentIds = $user->reservations()
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->pluck('equipment_id');

        $documents = Document::query()
            ->with(['equipment', 'users' => fn ($q) => $q->where('users.id', $user->id)])
            ->whereIn('equipment_id', $equipmentIds);

        $filteredDocuments = $this->filteredDocuments($documents, $request, $user->id, 'required');
        $checklistProgress = [
            'total' => (clone $filteredDocuments)->count(),
            'acknowledged' => (clone $filteredDocuments)->whereHas('users', fn ($users) => $users
                ->where('users.id', $user->id)->whereNotNull('document_user.acknowledged_at'))->count(),
        ];
        $documents = $filteredDocuments->orderBy($request->input('sort', 'title'), $request->input('direction', 'asc'))
            ->orderBy('documents.id')->paginate(10)->withQueryString();
        $filters = $request->only(['required', 'acknowledged']);
        $sort = $request->input('sort', 'title');
        $direction = $request->input('direction', 'asc');

        return view('documents.checklist', compact('documents', 'filters', 'sort', 'direction', 'checklistProgress'));
    }

    private function filteredDocuments($query, DocumentFilterRequest $request, ?int $userId = null, string $defaultRequired = 'all')
    {
        $required = $request->input('required', $defaultRequired);
        if ($required !== 'all') {
            $query->where('is_required', $required === 'required');
        }

        if ($request->filled('acknowledged')) {
            $acknowledged = fn ($users) => $users
                ->when($userId !== null, fn ($users) => $users->where('users.id', $userId))
                ->whereNotNull('document_user.acknowledged_at');
            $request->input('acknowledged') === 'yes'
                ? $query->whereHas('users', $acknowledged)
                : $query->whereDoesntHave('users', $acknowledged);
        }

        return $query;
    }

    public function store(StoreDocumentRequest $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('create', [Document::class, $equipment]);

        $file = $request->file('file');

        // Private disk (storage/app/private): not reachable by pasting a URL.
        $path = $file->store('documents', 'local');

        try {
            DB::transaction(function () use ($equipment, $request, $file, $path) {
                $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
                $this->authorize('create', [Document::class, $equipment]);
                $equipment->documents()->create([
                    'type' => $request->input('type'),
                    'title' => $request->input('title'),
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'is_required' => $request->boolean('is_required'),
                ]);
                ActivityRecorder::record('document.uploaded', $request->user(), $equipment, details: ['title' => $request->input('title')]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return back()->with('status', 'Document uploaded.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        // Compliance tracking applies to borrowers only.
        if ($request->user()->isBorrower()) {
            $request->user()->documents()->syncWithoutDetaching([
                $document->id => ['downloaded_at' => now()],
            ]);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function acknowledge(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('download', $document);

        $pivot = $request->user()->documents()->where('documents.id', $document->id)->first()?->pivot;

        if (! $pivot?->downloaded_at) {
            throw ValidationException::withMessages(['document' => 'Download the document before acknowledging it.']);
        }

        $request->user()->documents()->updateExistingPivot($document->id, ['acknowledged_at' => now()]);

        ActivityRecorder::record('document.acknowledged', $request->user(), $document->equipment, details: ['title' => $document->title]);

        return back()->with('status', 'Acknowledged.');
    }

    public function acknowledgeAll(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('reserve_equipment');
        $this->authorize('view', $equipment);
        $user = $request->user();

        $count = DB::transaction(function () use ($equipment, $user) {
            Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            $required = $equipment->documents()->where('is_required', true)->lockForUpdate()->get();
            $downloaded = $user->documents()->whereIn('documents.id', $required->modelKeys())
                ->get()->keyBy('id');
            $missing = $required->filter(fn ($document) => ! $downloaded->get($document->id)?->pivot?->downloaded_at);
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'documents' => 'Download every required PDF first: '.$missing->pluck('title')->implode(', ').'.',
                ]);
            }
            $rows = [];
            foreach ($required as $document) {
                if (! $downloaded->get($document->id)->pivot->acknowledged_at) {
                    $rows[$document->id] = ['acknowledged_at' => now()];
                }
            }
            $user->documents()->syncWithoutDetaching($rows);

            return count($rows);
        });

        if ($count > 0) {
            ActivityRecorder::record('documents.acknowledged-all', $user, $equipment, details: ['count' => $count]);
        }

        return back()->with('status', $count > 0
            ? "Marked {$count} required documents as read for {$equipment->name}."
            : "All required documents for {$equipment->name} are already acknowledged.");
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $path = $document->file_path;
        ActivityRecorder::record('document.deleted', $request->user(), $document->equipment, details: ['title' => $document->title]);
        $document->delete();

        // Seeded demo documents share one placeholder file, so only remove files nobody else uses.
        if (! Document::where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('status', 'Document deleted.');
    }
}
