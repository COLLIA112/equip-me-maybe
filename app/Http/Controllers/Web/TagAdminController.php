<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagAdminRequest;
use App\Models\Tag;
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
        Tag::create($request->validated());

        return back()->with('status', 'Equipment tag created.');
    }

    public function update(TagAdminRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return back()->with('status', 'Equipment tag updated.');
    }

    public function destroy(Tag $tag)
    {
        Gate::authorize('manage_equipment');
        DB::transaction(function () use ($tag) {
            $tag = Tag::whereKey($tag->id)->lockForUpdate()->firstOrFail();
            if ($tag->equipment()->exists()) {
                throw ValidationException::withMessages(['tag' => 'This tag is assigned to equipment. Remove its assignments before deleting it.']);
            }
            $tag->delete();
        });

        return back()->with('status', 'Unused equipment tag deleted.');
    }
}
