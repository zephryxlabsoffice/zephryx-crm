<?php

namespace App\Http\Controllers;

use App\Support\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    /**
     * POST /theme — persist the light/dark preference (foundation spec §3.2).
     *
     * CSRF-protected like every state-changing request (§6). The submitted
     * value is validated against the allow-list rather than trusted, because it
     * is echoed back into the `data-theme` attribute on every later page.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(Theme::available())],
        ]);

        $theme = $validated['theme'];

        // Once accounts exist, the choice also persists to the user's profile
        // so it follows them across devices (spec §7).
        if ($user = $request->user()) {
            $user->forceFill(['theme_preference' => $theme])->save();
        }

        $response = $request->expectsJson() || $request->ajax()
            ? response()->json(['theme' => $theme])
            : redirect()->back();

        return $response->withCookie(Theme::cookie($theme));
    }
}
