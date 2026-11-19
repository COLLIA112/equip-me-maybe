<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PreferencesController extends Controller
{
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $preferences = DB::transaction(function () use ($request, $actor) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $preferences = $user->preferences ?? [];
            $preferences['theme'] = $request->validated('theme');
            if ($request->has('remember_filters')) {
                $preferences['remember_filters'] = $request->boolean('remember_filters');
                if (! $preferences['remember_filters']) {
                    unset($preferences['lists']);
                }
            }
            $user->forceFill(['preferences' => $preferences])->save();

            return $preferences;
        }, 3);
        $actor->forceFill(['preferences' => $preferences])->syncOriginalAttribute('preferences');

        return redirect()->route('profile.edit')->with('status', 'Your display preferences were saved.');
    }
}
