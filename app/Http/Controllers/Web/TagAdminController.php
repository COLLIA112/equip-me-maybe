<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagAdminRequest;
use App\Models\Tag;
use App\Services\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TagAdminController extends Controller
{
    public function index()
    {
        Gate::authorize('manage_equipment');
        $tags = Tag::withCount('equipment')->orderBy('name')->paginate(15)->withQueryString();

        return view('tags.index', compact('tags'));
    }

    public function store(TagAdminRequest $request)
    {
        DB::transaction(function () use ($request) {
            $tag = Tag::create($request->validated());
            $this->record('tag.created', $request, $tag);
        });

        return back()->with('status', 'Equipment tag created.');
    }

    public function update(TagAdminRequest $request, Tag $tag)
    {
        DB::transaction(function () use ($request, $tag) {
            $tag->update($request->validated());
            $this->record('tag.updated', $request, $tag);
        });

        return back()->with('status', 'Equipment tag updated.');
    }

    public function destroy(Request $request, Tag $tag)
    {
        Gate::authorize('manage_equipment');
        DB::transaction(function () use ($tag, $request) {
            $tag = Tag::whereKey($tag->id)->lockForUpdate()->firstOrFail();
            if ($tag->equipment()->exists()) {
                throw ValidationException::withMessages(['tag' => 'This tag is assigned to equipment. Remove its assignments before deleting it.']);
            }
            $this->record('tag.deleted', $request, $tag);
            $tag->delete();
        });

        return back()->with('status', 'Unused equipment tag deleted.');
    }

    private function record(string $event, Request $request, Tag $tag): void
    {
        $actor = $request->user();
        ActivityRecorder::record($event, $actor, details: [
            'tag_id' => $tag->id, 'name' => $tag->name,
            'department_id' => $actor->department_id, 'lab_id' => $actor->lab_id,
        ]);
    }
}
