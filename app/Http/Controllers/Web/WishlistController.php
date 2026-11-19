<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Services\AvailabilityNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('reserve_equipment');
        $equipment = $request->user()->wishlist()->with(['lab.department'])->orderBy('name')->paginate(12)->withQueryString();

        return view('wishlist.index', compact('equipment'));
    }

    public function store(Request $request, Equipment $equipment)
    {
        Gate::authorize('reserve_equipment');
        DB::transaction(function () use ($request, $equipment) {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            $user = $request->user();
            if (! $user->wishlist()->where('equipment.id', $equipment->id)->exists()) {
                $user->wishlist()->attach($equipment->id, [
                    'notified_at' => AvailabilityNotifier::isAvailableNow($equipment) ? now() : null,
                ]);
            }
        });

        return back()->with('status', 'Saved to your wishlist. We will notify you in the app when it becomes available.');
    }

    public function destroy(Request $request, Equipment $equipment)
    {
        Gate::authorize('reserve_equipment');
        $request->user()->wishlist()->detach($equipment->id);

        return back()->with('status', 'Equipment removed from your wishlist.');
    }
}
