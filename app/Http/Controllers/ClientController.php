<?php

namespace App\Http\Controllers;

use App\Mail\AccountInviteMail;
use App\Models\Client;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\PasswordResets;
use App\Support\ClientDirectory;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Clients — the organisations this company works for (foundation spec §12).
 *
 * Reads the `clients` table through App\Support\ClientDirectory, which owns the
 * row shape the views expect. Filtering and pagination happen in SQL.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THERE IS NO DELETE, AT ANY PERMISSION
 *
 * Projects, invoices, tickets and meetings will all point back at a client, and
 * an invoice whose client has been removed is a record nobody can explain. A
 * client that is no longer worked with is marked `completed`; one added by
 * mistake is corrected, not erased.
 *
 * PORTAL ACCESS IS A SEPARATE ACT
 *
 * Creating a client does not create a login. §1 says every account is created
 * by an administrator, and giving somebody the ability to read a company's
 * invoices is a bigger decision than recording that the company exists — so it
 * is its own route, its own permission, and its own audit entry.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ClientController extends Controller
{
    protected const PER_PAGE = 7;

    public function __construct(protected Rbac $rbac, protected AuditLog $audit)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Client::STATUSES)],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;

        $query = ClientDirectory::query($search !== '' ? $search : null, $status);

        return response()->view('clients.index', [
            'activeNav' => 'clients',
            'clients' => ClientDirectory::paginate($query, self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'filtered' => $search !== '' || $status !== null,
            'stats' => ClientDirectory::stats(),
            'activity' => ClientDirectory::activity(),
            // Answered by the Meetings module. An empty rail states that
            // nothing is scheduled, which is true, rather than inventing three.
            'meetings' => [],
            // Drawn once here rather than asked per row: the table renders an
            // action menu on every line, and a permission check inside the loop
            // is the same answer computed seven times.
            'mayCreate' => $this->rbac->can($request->user(), 'clients.create'),
            'mayEdit' => $this->rbac->can($request->user(), 'clients.edit'),
        ]);
    }

    public function show(Request $request, string $client): Response
    {
        $record = $this->find($client);

        return response()->view('clients.show', [
            'activeNav' => 'clients',
            'client' => ClientDirectory::row($record),
            'record' => $record,
            'accounts' => $record->accounts()->orderBy('name')->get(),
            'history' => $this->audit->entriesFor('client', $record->reference),
            'mayEdit' => $this->rbac->can($request->user(), 'clients.edit'),
            'mayInvite' => $this->rbac->can($request->user(), 'clients.invite'),
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('clients.form', [
            'activeNav' => 'clients',
            'client' => null,
            'reference' => $this->nextReference(),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $client = Client::create($data + ['reference' => $this->nextReference()]);

        $this->audit->record(
            action: AuditLog::CLIENT_CREATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $client->reference,
            after: $this->describe($client->fresh('accountManager')),
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $client->reference])
            ->with('status', $client->name.' was added.')
            ->with('status_tone', 'success');
    }

    public function edit(Request $request, string $client): Response
    {
        $record = $this->find($client);

        return response()->view('clients.form', [
            'activeNav' => 'clients',
            'client' => $record,
            'reference' => $record->reference,
        ] + $this->formOptions());
    }

    public function update(Request $request, string $client): RedirectResponse
    {
        $record = $this->find($client);
        $data = $this->validated($request, $record);

        $before = $this->describe($record);

        $record->update($data);
        $record->refresh()->load('accountManager');

        $this->audit->record(
            action: AuditLog::CLIENT_UPDATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $record->reference,
            before: $before,
            after: $this->describe($record),
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $record->reference])
            ->with('status', 'Record updated.')
            ->with('status_tone', 'success');
    }

    /**
     * Move a client between engagement states.
     *
     * Separate from the edit form because it is the one field with consequences
     * beyond the record — the list, the KPIs and eventually the invoicing all
     * key on it — and because it wants its own audit action, so "who put this
     * client on hold, and when" is a question with a one-line answer.
     *
     * It does NOT touch anybody's login. A completed client's people can still
     * sign in and read their old invoices; taking that away is done per account
     * in the Admin Panel, which is where account status lives.
     */
    public function status(Request $request, string $client): RedirectResponse
    {
        $record = $this->find($client);

        $data = $request->validate([
            'status' => ['required', Rule::in(Client::STATUSES)],
        ]);

        $before = $record->status;

        if ($before === $data['status']) {
            // Idempotent rather than a second identical audit entry: a
            // double-submitted form must not make it look as though somebody
            // changed the same value twice.
            return redirect()
                ->route('clients.show', ['client' => $record->reference])
                ->with('status', $record->name.' is already '.$this->words($before).'.')
                ->with('status_tone', 'info');
        }

        $record->update(['status' => $data['status']]);

        $this->audit->record(
            action: AuditLog::CLIENT_STATUS_CHANGED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $record->reference,
            before: $before,
            after: $data['status'],
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $record->reference])
            ->with('status', $record->name.' is now '.$this->words($data['status']).'.')
            ->with('status_tone', $data['status'] === 'active' ? 'success' : 'info');
    }

    /**
     * Give somebody at this client access to the portal.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS CREATES AN ACCOUNT THAT CAN READ A COMPANY'S INVOICES
     *
     * Which is why `clients.invite` is sensitive and separate from
     * `clients.edit`: correcting a phone number and handing over a login are
     * not the same size of act.
     *
     * `client_ref` is set to the client's REFERENCE, and that column is the
     * whole of the portal's ownership rule (§6). Setting it to a name would
     * mean renaming a company silently detached its portal from its records.
     *
     * Nobody types the password. The account is created with a random one
     * nobody sees and the person sets their own through a single-use link — the
     * same reason as on the employee form.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function invite(Request $request, string $client): RedirectResponse
    {
        $record = $this->find($client);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
        ]);

        $account = DB::transaction(function () use ($data, $record) {
            return User::create([
                'user_id' => $this->nextClientAccountId(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'account_type' => Realm::CLIENT,
                // No Employee base: a client has no attendance, leave or
                // payslips at all (§2.1).
                'staff_kind' => null,
                'status' => 'active',
                'client_ref' => $record->reference,
            ]);
        });

        $token = app(PasswordResets::class)->issue($account, $request);

        Mail::to($account->email)->send(new AccountInviteMail(
            url: route('password.reset.form', ['token' => $token]),
            name: $account->name,
            staffId: $account->user_id,
            addedBy: $request->user()->name,
        ));

        $this->audit->record(
            action: AuditLog::CLIENT_INVITED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $record->reference,
            after: $account->name.' ('.$account->email.') was given portal access as '.$account->user_id,
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $record->reference])
            ->with('status', $account->name.' has been emailed a link to set their password.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function find(string $reference): Client
    {
        return Client::query()
            ->with('accountManager')
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Client $existing = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:160',
                // Unique because the whole application refers to clients by
                // name on screen, and two identical rows on a list are
                // indistinguishable to whoever has to pick one.
                Rule::unique('clients', 'name')->ignore($existing?->id),
            ],
            'industry' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(Client::STATUSES)],

            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:32'],

            /*
             * A staff account, and an ACTIVE one. Somebody whose record was
             * closed last month is not who a client should be told to contact,
             * and the rule is here rather than only in the dropdown because a
             * dropdown is not where that is enforced.
             */
            'account_manager_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('account_type', Realm::STAFF)
                    ->where('status', 'active'),
            ],

            'signed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * The dropdowns.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'managers' => User::query()
                ->where('account_type', Realm::STAFF)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'user_id']),
        ];
    }

    /**
     * The next client reference.
     *
     * Derived from the highest existing one rather than from a count, because a
     * count reissues a reference the moment anything is removed — and a
     * reference that has belonged to two clients is one that makes an invoice
     * trail ambiguous.
     */
    protected function nextReference(): string
    {
        $highest = Client::query()
            ->where('reference', 'like', 'CLT%')
            ->selectRaw('max(cast(substr(reference, 4) as integer)) as n')
            ->value('n');

        return 'CLT'.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * The next portal account id. `CLI`, not `CLT`: an account and the client
     * behind it are different things and must not share an identifier shape.
     */
    protected function nextClientAccountId(): string
    {
        $highest = User::query()
            ->where('account_type', Realm::CLIENT)
            ->where('user_id', 'like', 'CLI%')
            ->selectRaw('max(cast(substr(user_id, 4) as integer)) as n')
            ->value('n');

        return 'CLI'.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * One line describing a record, for the audit log's before and after.
     */
    protected function describe(Client $client): string
    {
        return implode(' · ', array_filter([
            $client->name,
            $client->industry,
            $this->words($client->status),
            $client->contact_email,
            $client->accountManager?->name,
        ]));
    }

    /**
     * A status as it is written on screen, so the audit log and the pill agree.
     */
    protected function words(string $status): string
    {
        return match ($status) {
            'on_hold' => 'on hold',
            'review' => 'in review',
            default => $status,
        };
    }
}
