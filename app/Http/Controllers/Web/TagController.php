<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Tag management. Tags are shared by every lab, so:
 *  - lab managers and department heads may list and create tags (route middleware: manage_equipment)
 *  - only department heads may rename or delete a tag (it changes every lab's equipment)
 */
class TagController extends Controller
{
    public function index()
    {
        return view('tags.index', [
            'tags' => Tag::withCount('equipment')->orderBy('name')->get(),
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = Tag::create(['name' => $request->string('name')->trim()->toString()]);

        return redirect()->route('tags.index')->with('status', "Tag \"{$tag->name}\" created.");
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('view_department_reports');

        $tag->update(['name' => $request->string('name')->trim()->toString()]);

        return redirect()->route('tags.index')->with('status', "Tag renamed to \"{$tag->name}\".");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('view_department_reports');

        $name = $tag->name;
        $tag->equipment()->detach();
        $tag->delete();

        return redirect()->route('tags.index')->with('status', "Tag \"{$name}\" deleted.");
    }
}
