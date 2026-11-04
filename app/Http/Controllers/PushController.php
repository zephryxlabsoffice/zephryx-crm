<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Browser push: turning it on and off, and what public/sw.js shows when one arrives.
 */
class PushController extends Controller
{
    /**
     * POST /push/subscribe
     *
     * A browser that was somebody else's moves to whoever is signed in now.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $endpoint = $this->endpoint($request);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            ['user_id' => $request->user()->id, 'endpoint' => $endpoint],
        );

        return response()->json(['subscribed' => true]);
    }

    /**
     * POST /push/unsubscribe - only ever the signed-in person's own.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $this->endpoint($request)))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    /**
     * GET /push/latest - the words for a push that has just arrived.
     */
    public function latest(Request $request): JsonResponse
    {
        $latest = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('kind', 'announcement')
            ->whereNull('read_at')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'title' => $latest?->title ?? 'New announcement',
            'body' => $latest?->body ?? 'Open the CRM to read it.',
            'url' => $latest?->link() ?? route('announcements.index'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    protected function endpoint(Request $request): string
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string', 'url', 'max:2000']])['endpoint'];

        if (! WebPush::isAllowedEndpoint($endpoint)) {
            throw ValidationException::withMessages(['endpoint' => 'That is not a push service this site can use.']);
        }

        return $endpoint;
    }
}
