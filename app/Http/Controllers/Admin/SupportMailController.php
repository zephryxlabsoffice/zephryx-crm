<?php

namespace App\Http\Controllers\Admin;

use App\Models\SupportMailbox;
use App\Support\Audit\AuditLog;
use App\Support\SupportMail\GmailClient;
use App\Support\SupportMail\SupportMailIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin > Integrations > Support mailbox (owner, 2026-11-06).
 *
 * The owner pastes the OAuth client from Google Cloud once, then signs in as the
 * mailbox support@ is delivered to (contact@). The secret and the token are
 * write-only, like the service account key: no page ever shows them.
 */
class SupportMailController extends Controller
{
    protected const STATE = 'support_mail_oauth_state';

    public function __construct(protected AuditLog $audit) {}

    /**
     * POST /admin/integrations/support-mail - the client, the address, the keywords.
     */
    public function save(Request $request): RedirectResponse
    {
        abort_unless(SupportMailbox::ready(), 404);

        $mailbox = SupportMailbox::current();

        $data = $request->validate([
            'address' => ['required', 'email', 'max:190'],
            'account_email' => ['required', 'email', 'max:190'],
            'client_id' => ['required', 'string', 'max:255'],
            // Blank keeps the one already saved.
            'client_secret' => [$mailbox->hasClient() ? 'nullable' : 'required', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:5000'],
        ]);

        $clientChanged = $mailbox->client_id !== $data['client_id'] || filled($data['client_secret'] ?? null);

        $mailbox->fill([
            'address' => strtolower($data['address']),
            'account_email' => strtolower($data['account_email']),
            'client_id' => trim($data['client_id']),
            'keywords' => $data['keywords'] ?? SupportMailbox::DEFAULT_KEYWORDS,
        ]);

        if (filled($data['client_secret'] ?? null)) {
            $mailbox->client_secret = trim($data['client_secret']);
        }

        // A different client cannot use the old sign-in.
        if ($clientChanged) {
            $mailbox->refresh_token = null;
        }

        $mailbox->save();

        $this->audit->record(
            action: AuditLog::SUPPORT_MAIL_SAVED,
            actor: $request->user(),
            entityType: 'support_mailbox',
            entityId: (string) $mailbox->id,
            after: $mailbox->address.' via '.$mailbox->account_email,
            request: $request,
        );

        return redirect()->route('admin.integrations')
            ->with('status', $mailbox->isConnected() ? 'Saved.' : 'Saved. Now press "Sign in with Google" and sign in as '.$mailbox->account_email.'.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /admin/integrations/support-mail/connect - off to Google.
     */
    public function connect(Request $request): RedirectResponse
    {
        $mailbox = SupportMailbox::current();

        abort_unless(SupportMailbox::ready() && $mailbox->hasClient(), 404);

        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);

        return redirect()->away(GmailClient::authorizationUrl($mailbox, $this->redirectUri(), $state));
    }

    /**
     * GET /admin/integrations/support-mail/callback - Google sends the admin back.
     */
    public function callback(Request $request): RedirectResponse
    {
        $mailbox = SupportMailbox::current();
        $expected = $request->session()->pull(self::STATE);

        abort_unless(SupportMailbox::ready() && $mailbox->hasClient(), 404);

        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->back('The sign-in could not be confirmed. Try again.', 'danger');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->back('Google did not sign in: '.($request->query('error') ?? 'no code').'.', 'danger');
        }

        try {
            $result = GmailClient::exchange($mailbox, (string) $request->query('code'), $this->redirectUri());
        } catch (Throwable $e) {
            report($e);

            return $this->back($e->getMessage(), 'danger');
        }

        if (filled($mailbox->account_email) && strcasecmp($result['email'], $mailbox->account_email) !== 0) {
            return $this->back('That was '.$result['email'].'. Sign in as '.$mailbox->account_email.', the mailbox support mail arrives in.', 'danger');
        }

        $mailbox->fill([
            'refresh_token' => $result['refresh_token'],
            'account_email' => strtolower($result['email']),
            'connected_at' => now(),
            'connected_by' => $request->user()->id,
            'last_error' => null,
        ])->save();

        $this->audit->record(
            action: AuditLog::SUPPORT_MAIL_CONNECTED,
            actor: $request->user(),
            entityType: 'support_mailbox',
            entityId: (string) $mailbox->id,
            after: 'Signed in as '.$result['email'].', reading mail to '.$mailbox->address,
            request: $request,
        );

        return $this->back('Connected. Mail to '.$mailbox->address.' from now on becomes tickets, checked every five minutes once the Cron Job is set up.', 'success');
    }

    /**
     * POST /admin/integrations/support-mail/check - run it now.
     */
    public function check(SupportMailIntake $intake): RedirectResponse
    {
        abort_unless(SupportMailbox::ready() && SupportMailbox::current()->isConnected(), 404);

        $counts = $intake->run();
        $error = SupportMailbox::current()->last_error;

        return $error !== null
            ? $this->back('The check failed: '.$error, 'danger')
            : $this->back("Checked: {$counts['tickets']} new tickets, {$counts['replies']} replies, {$counts['ignored']} ignored, {$counts['failed']} failed.", 'success');
    }

    /**
     * POST /admin/integrations/support-mail/disconnect
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $mailbox = SupportMailbox::current();

        abort_unless(SupportMailbox::ready() && $mailbox->exists, 404);

        $mailbox->fill(['refresh_token' => null, 'connected_at' => null, 'connected_by' => null])->save();

        $this->audit->record(
            action: AuditLog::SUPPORT_MAIL_DISCONNECTED,
            actor: $request->user(),
            entityType: 'support_mailbox',
            entityId: (string) $mailbox->id,
            request: $request,
        );

        return $this->back('Disconnected. Mail to support is no longer read.', 'warning');
    }

    protected function redirectUri(): string
    {
        return route('admin.integrations.support-mail.callback');
    }

    protected function back(string $message, string $tone): RedirectResponse
    {
        return redirect()->route('admin.integrations')->with('status', $message)->with('status_tone', $tone);
    }
}
