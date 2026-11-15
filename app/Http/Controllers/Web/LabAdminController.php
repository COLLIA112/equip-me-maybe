<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabAdminRequest;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LabAdminController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view_department_reports');
        $labs = Lab::where('department_id', $request->user()->department_id)
            ->withCount(['equipment', 'users'])->orderBy('name')->paginate(12)->withQueryString();

        return view('department.labs.index', compact('labs'));
    }

    public function store(LabAdminRequest $request)
    {
        Lab::create($request->validated() + ['department_id' => $request->user()->department_id]);

        return back()->with('status', 'Laboratory created. Assign its lab manager in Users and roles.');
    }

    public function update(LabAdminRequest $request, Lab $lab)
    {
        $lab->update($request->validated());

        return back()->with('status', 'Laboratory updated.');
    }

    public function destroy(Request $request, Lab $lab)
    {
        Gate::authorize('view_department_reports');
        abort_unless($request->user()->headsDepartment($lab->department_id), 403);
        DB::transaction(function () use ($lab) {
            $lab = Lab::whereKey($lab->id)->lockForUpdate()->firstOrFail();
            if ($lab->equipment()->exists() || $lab->users()->exists()) {
                throw ValidationException::withMessages(['lab' => 'This laboratory has equipment or assigned users. Move them before deleting it to preserve history.']);
            }
            $lab->delete();
        });

        return back()->with('status', 'Empty laboratory deleted.');
    }
}
