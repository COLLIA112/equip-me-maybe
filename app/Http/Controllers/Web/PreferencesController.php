<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;

class PreferencesController extends Controller
{
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $preferences = $user->fresh()->preferences ?? [];
        $preferences['theme'] = $request->validated('theme');
        if ($request->has('remember_filters')) {
            $preferences['remember_filters'] = $request->boolean('remember_filters');
            if (! $preferences['remember_filters']) {
                unset($preferences['lists']);
            }
        }
        $user->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('profile.edit')->with('status', 'Your display preferences were saved.');
    }
}
