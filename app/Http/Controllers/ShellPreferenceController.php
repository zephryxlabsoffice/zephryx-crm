<?php

namespace App\Http\Controllers;

use App\Support\Shell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Persists the shell's per-viewer preferences: sidebar collapse and density.
 *
 * CSRF-protected and validated against an allow-list, like the theme (§6) —
 * both values are echoed into attributes on <html> on every later page.
 */
class ShellPreferenceController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'sidebar' => ['nullable', Rule::in(Shell::SIDEBAR_STATES)],
            'density' => ['nullable', Rule::in(Shell::DENSITIES)],
        ]);

        $response = $request->expectsJson() || $request->ajax()
            ? response()->json($validated)
            : redirect()->back();

        if (! empty($validated['sidebar'])) {
            $response->withCookie(Shell::sidebarCookie($validated['sidebar']));
        }

        if (! empty($validated['density'])) {
            $response->withCookie(Shell::densityCookie($validated['density']));
        }

        return $response;
    }
}
