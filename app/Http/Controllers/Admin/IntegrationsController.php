<?php

namespace App\Http\Controllers\Admin;

use App\Models\GoogleConnection;
use App\Support\Audit\AuditLog;
use App\Support\Google\DriveClient;
use App\Support\Google\GoogleAuth;
use App\Support\Google\GoogleServiceAccountKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Throwable;

/**
 * The one Google connection this company has.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE KEY IS WRITE-ONLY, THE SAME RULE THE IDENTITY CARD FOLLOWS
 *
 * `index()` never passes the stored key to the view — not masked, not
 * partial, not in a hidden field. What the screen shows after connecting is
 * the service account's email, the key's fingerprint and when it was
 * connected: everything needed to tell one key from another, nothing that
 * could be lifted from the markup (plan doc, "Connecting it", rule 2).
 *
 * WHY THIS ONLY LIVES IN ADMIN_BASE, NOT ITS OWN ROLE
 *
 * The plan doc calls for "CEO and System Administrator" to hold this. The
 * Admin Panel realm has one account today and staff sessions are refused
 * here entirely (routes/admin.php), so that split does not exist yet — see
 * Rbac::ADMIN_BASE for the decision record. This controller is written
 * against the permission key existing on its own, so nothing here has to
 * change when that split is eventually built.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class IntegrationsController extends Controller
{
    public function __construct(protected AuditLog $audit) {}

    public function index(): Response
    {
        return response()->view('admin.integrations.index', [
            'activeNav' => 'integrations',
            'connection' => GoogleConnection::current(),
        ]);
    }

    /**
     * POST /admin/integrations/connect — paste a key, or replace one.
     */
    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_account_key' => ['required', 'string'],
            'shared_drive_id' => ['required', 'string', 'max:255'],
            'calendar_id' => ['nullable', 'string', 'max:255'],
            'impersonate_email' => ['nullable', 'email', 'max:255'],
        ]);

        try {
            $key = GoogleServiceAccountKey::parse($validated['service_account_key']);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.integrations')
                ->withErrors(['service_account_key' => $e->getMessage()])
                ->withInput($request->except('service_account_key'));
        }

        $connection = GoogleConnection::current();
        $wasConnected = $connection->isConnected();
        $previousFingerprint = $connection->key_fingerprint;

        $connection->fill([
            'service_account_email' => $key->clientEmail,
            'service_account_key' => $key->raw,
            'key_fingerprint' => $key->fingerprint(),
            'shared_drive_id' => $validated['shared_drive_id'],
            'calendar_id' => $validated['calendar_id'] ?? null,
            'impersonate_email' => $validated['impersonate_email'] ?? null,
            'connected_at' => now(),
            'connected_by' => $request->user()->id,
        ])->save();

        $this->audit->record(
            action: $wasConnected ? AuditLog::GOOGLE_RECONNECTED : AuditLog::GOOGLE_CONNECTED,
            actor: $request->user(),
            entityType: 'google_connection',
            entityId: (string) $connection->id,
            before: $wasConnected ? 'fingerprint '.$previousFingerprint : null,
            after: 'fingerprint '.$key->fingerprint().', account '.$key->clientEmail,
            request: $request,
        );

        return redirect()->route('admin.integrations')
            ->with('status', 'Connected. Run "Test connection" to confirm it can write to the Shared Drive before anybody relies on it.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/integrations/test — creates and deletes a file for real.
     *
     * A "Test connection" that faked success would be exactly the mistake
     * GoogleMeetProvider's header comment refuses to make for meetings: a
     * button that looks like it works and goes nowhere.
     */
    public function test(Request $request): RedirectResponse
    {
        $connection = GoogleConnection::current();

        abort_if(! $connection->isConnected(), 404);

        try {
            $auth = new GoogleAuth(GoogleServiceAccountKey::parse($connection->service_account_key));
            (new DriveClient($auth, (string) $connection->shared_drive_id))->testConnection();
        } catch (Throwable $e) {
            $this->audit->record(
                action: AuditLog::GOOGLE_TEST_FAILED,
                actor: $request->user(),
                entityType: 'google_connection',
                entityId: (string) $connection->id,
                after: 'fingerprint '.$connection->key_fingerprint.' — '.$e->getMessage(),
                request: $request,
            );

            return redirect()->route('admin.integrations')
                ->with('status', 'The test failed: '.$e->getMessage())
                ->with('status_tone', 'danger');
        }

        $this->audit->record(
            action: AuditLog::GOOGLE_TEST_SUCCEEDED,
            actor: $request->user(),
            entityType: 'google_connection',
            entityId: (string) $connection->id,
            after: 'fingerprint '.$connection->key_fingerprint,
            request: $request,
        );

        return redirect()->route('admin.integrations')
            ->with('status', 'Confirmed — a test file was created and deleted in the Shared Drive.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/integrations/disconnect — removes the key, keeps the rest.
     *
     * `calendar_id`, `impersonate_email` and `shared_drive_id` are left in
     * place: they are not secrets, and reconnecting with a new key should not
     * mean retyping them.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $connection = GoogleConnection::current();

        abort_if(! $connection->isConnected(), 404);

        $fingerprint = $connection->key_fingerprint;

        $connection->fill([
            'service_account_email' => null,
            'service_account_key' => null,
            'key_fingerprint' => null,
            'connected_at' => null,
            'connected_by' => null,
        ])->save();

        $this->audit->record(
            action: AuditLog::GOOGLE_DISCONNECTED,
            actor: $request->user(),
            entityType: 'google_connection',
            entityId: (string) $connection->id,
            before: 'fingerprint '.$fingerprint,
            request: $request,
        );

        return redirect()->route('admin.integrations')
            ->with('status', 'Disconnected. Drive uploads and Calendar meetings will fail until this is reconnected.')
            ->with('status_tone', 'warning');
    }
}
