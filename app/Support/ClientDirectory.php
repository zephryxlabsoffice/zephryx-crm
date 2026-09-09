<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The client list, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ROW SHAPE IS A CONTRACT WITH THE VIEWS
 *
 * Rows come back as arrays in the shape DemoClients returned, because the table
 * template already reads it and is already tested against it. Handing the views
 * models instead would have turned a data-source change into a rewrite of
 * markup that was working.
 *
 * Two of those keys are now NULL rather than a value, and deliberately:
 *
 *   `project`  belongs to the Projects module. A client with three projects has
 *              no single value to put in the column, and one with none has no
 *              value at all.
 *   `payment`  is the state of that client's invoices, which is the Invoices
 *              module's question.
 *
 * Both render as "—" until those tables exist. The alternative — a stored
 * column somebody keeps in step by hand — is the mistake the employees table
 * avoided with `on_leave`, for exactly the same reason.
 *
 * `activity` is real today, because the audit log is: it is what has actually
 * been recorded against the client, by whom, rather than a sentence invented to
 * fill a rail.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ClientDirectory
{
    /**
     * The list query, filtered.
     *
     * Filtered in SQL, not over a loaded collection: the page this replaced
     * loaded every client and filtered in PHP, which is fine at ten and a full
     * table scan per keystroke at any size worth having a search box for.
     *
     * @return Builder<Client>
     */
    public static function query(?string $search = null, ?string $status = null): Builder
    {
        return Client::query()
            ->with('accountManager')
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                // Escaped, so a client called "100%" searches for itself rather
                // than for everything.
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('industry', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_email', 'like', $like);
            }))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->orderBy('name');
    }

    /**
     * One row in the shape the table reads.
     *
     * @return array<string, mixed>
     */
    public static function row(Client $client): array
    {
        return [
            'reference' => $client->reference,
            'name' => $client->name,
            'industry' => $client->industry,
            'status' => $client->status,
            'manager' => $client->accountManager?->name,
            'signed' => $client->signed_on?->toDateString(),

            // Answered by Projects and Invoices when those land — see the head
            // of this class. Null, not a placeholder string: the view decides
            // how "we cannot say yet" is drawn.
            'project' => null,
            'payment' => null,

            'activity' => self::lastActivity($client->reference),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        return self::query()->get()->map(fn (Client $c) => self::row($c));
    }

    /**
     * @param  Builder<Client>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage)->withQueryString()->through(fn (Client $c) => self::row($c));
    }

    /**
     * The headline counts.
     *
     * `receivable`, `overdue`, `tickets` and `unresolved` come from Invoices and
     * Tickets. They report zero until those modules have tables, rather than
     * carrying the handover's ₹1,85,000 into an application where nobody can
     * say where the figure came from.
     *
     * @return array<string, int>
     */
    public static function stats(): array
    {
        return [
            'total' => Client::count(),
            'active' => Client::active()->count(),
            'receivable' => 0,
            'overdue' => 0,
            'tickets' => 0,
            'unresolved' => 0,
        ];
    }

    /**
     * The industries a filter may offer — the ones a client is actually in.
     *
     * @return list<string>
     */
    public static function industriesInUse(): array
    {
        return Client::query()
            ->whereNotNull('industry')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->all();
    }

    /**
     * Recent client activity, from the audit log (§6).
     *
     * What was actually recorded, by whom — not a generated narrative. An empty
     * rail says nothing has been recorded, which is honest; a rail full of
     * invented events would not be.
     *
     * @return list<array<string, string>>
     */
    public static function activity(int $limit = 4): array
    {
        $names = Client::pluck('name', 'reference');

        return DB::table('audit_log')
            ->where('entity_type', 'client')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'client' => $names[$row->entity_id] ?? $row->entity_id,
                'what' => self::phraseFor($row->action).' by '.$row->actor_label,
                'when' => Carbon::parse($row->created_at)->diffForHumans(),
                'tone' => 'tone-accent',
                'icon' => 'clients',
            ])
            ->all();
    }

    /**
     * When something last happened to one client, for the table's column.
     */
    protected static function lastActivity(string $reference): ?string
    {
        $at = DB::table('audit_log')
            ->where('entity_type', 'client')
            ->where('entity_id', $reference)
            ->max('created_at');

        return $at ? Carbon::parse($at)->diffForHumans() : null;
    }

    /**
     * An audit action, as a sentence somebody reads rather than a key.
     */
    protected static function phraseFor(string $action): string
    {
        return match ($action) {
            'client.created' => 'was added',
            'client.updated' => 'was updated',
            'client.status_changed' => 'changed status',
            'client.invited' => 'was given portal access',
            default => str_replace(['client.', '_'], ['', ' '], $action),
        };
    }
}
