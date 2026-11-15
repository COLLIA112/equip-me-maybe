<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Document Manager screen for one equipment item. */
    public function index(Equipment $equipment)
    {
        $this->authorize('create', [Document::class, $equipment]);

        return view('documents.index', ['equipment' => $equipment->load('documents')]);
    }

    /** Required Documents checklist for the borrower's open reservations. */
    public function checklist(Request $request)
    {
        $user = $request->user();

        $equipmentIds = $user->reservations()
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->pluck('equipment_id');

        $documents = Document::query()
            ->with(['equipment', 'users' => fn ($q) => $q->where('users.id', $user->id)])
            ->whereIn('equipment_id', $equipmentIds)
            ->where('is_required', true)
            ->get();

        return view('documents.checklist', compact('documents'));
    }

    public function store(StoreDocumentRequest $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('create', [Document::class, $equipment]);

        $file = $request->file('file');

        // Private disk (storage/app/private): not reachable by pasting a URL.
        $path = $file->store('documents', 'local');

        $equipment->documents()->create([
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'is_required' => $request->boolean('is_required'),
        ]);

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

        return back()->with('status', 'Acknowledged.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $path = $document->file_path;
        $document->delete();

        // Seeded demo documents share one placeholder file, so only remove files nobody else uses.
        if (! Document::where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('status', 'Document deleted.');
    }
}
