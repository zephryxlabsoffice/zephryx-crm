<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Rbac\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who sees a ticket, who may act on it, and where it goes when it is escalated.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PROCESS (owner, 2026-11-06)
 *
 * Everything lands with Support, except a client's technical issue, which goes
 * straight to a Technical Associate (v1.7.0, kept). Support solves it or
 * escalates; whoever it is escalated to solves it or escalates again, to their
 * seniors. A System Administrator or the CEO may take any ticket themselves, and
 * a Manager one of their own.
 *
 * NOBODY DECIDES THEIR OWN TICKET
 *
 * Whoever raised a ticket may comment on it and attach files, and nothing else:
 * no triage, no taking, no escalating, no resolving, and they are never offered
 * as its assignee. The CEO included: their ticket goes through the same process,
 * and where it would be escalated to the CEO it goes to a System Administrator.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class TicketWorkflow
{
    /** Every ticket, not only one's own. Support, the CEO and System Administrators. */
    public const VIEW_ALL = 'tickets.view.all';

    /** Taking any ticket oneself. The CEO and System Administrators. */
    public const TAKE = 'tickets.take';

    /** Routing, prioritising and escalating from the queue. */
    public const TRIAGE = 'tickets.triage';

    public static function employeeFor(?User $user): ?Employee
    {
        return $user === null ? null : Employee::where('user_id', $user->id)->first();
    }

    /**
     * The tickets this person may see.
     *
     * Everybody sees what they raised, what is with them, and what they escalated.
     * Somebody looking after a client sees that client's. A Manager (anybody who
     * triages) also sees their projects', and those of people who report to them or
     * are in a team they lead.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function visibleTo(Builder $query, ?User $user): Builder
    {
        $rbac = app(Rbac::class);

        if ($rbac->can($user, self::VIEW_ALL)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $employeeId = self::employeeFor($user)?->id ?? 0;
        $triages = $rbac->can($user, self::TRIAGE);

        return $query->where(fn (Builder $mine) => $mine
            ->where('raised_by', $employeeId)
            ->orWhere('assignee_id', $employeeId)
            ->orWhere('escalated_by', $employeeId)
            ->orWhereHas('client', fn (Builder $client) => $client->where('account_manager_id', $user->id))
            ->when($triages, fn (Builder $managed) => $managed
                ->orWhereHas('project', fn (Builder $project) => $project->where('manager_id', $employeeId))
                ->orWhereHas('raiser', fn (Builder $raiser) => $raiser
                    ->where('reports_to', $employeeId)
                    ->orWhereHas('teams', fn (Builder $team) => $team->where('lead_id', $employeeId)))));
    }

    public static function canSee(Ticket $ticket, ?User $user): bool
    {
        return self::visibleTo(Ticket::query()->whereKey($ticket->id), $user)->exists();
    }

    public static function isOwn(Ticket $ticket, ?Employee $employee): bool
    {
        return $employee !== null && $ticket->raised_by !== null && $ticket->raised_by === $employee->id;
    }

    /**
     * Triage, take, escalate and resolve all start here: never on one's own ticket,
     * and never on a closed one.
     */
    public static function mayDecide(Ticket $ticket, ?User $user): bool
    {
        return $ticket->status !== 'closed' && ! self::isOwn($ticket, self::employeeFor($user));
    }

    public static function mayTriage(Ticket $ticket, ?User $user): bool
    {
        return self::mayDecide($ticket, $user) && app(Rbac::class)->can($user, self::TRIAGE);
    }

    public static function mayTake(Ticket $ticket, ?User $user): bool
    {
        $employee = self::employeeFor($user);

        if ($employee === null || ! self::mayDecide($ticket, $user) || $ticket->assignee_id === $employee->id) {
            return false;
        }

        return app(Rbac::class)->can($user, self::TAKE)
            || in_array($employee->id, self::managersFor($ticket), true);
    }

    /**
     * Support, from the queue, or whoever has it now.
     */
    public static function mayWorkOn(Ticket $ticket, ?User $user): bool
    {
        if (! self::mayDecide($ticket, $user) || $ticket->status === 'resolved') {
            return false;
        }

        $employee = self::employeeFor($user);

        return app(Rbac::class)->can($user, self::TRIAGE)
            || ($employee !== null && $ticket->assignee_id === $employee->id);
    }

    /**
     * The Managers a ticket belongs to: its project's, its client's, and the
     * raiser's own manager or team lead, where they hold the Manager role.
     *
     * @return list<int> employee ids
     */
    public static function managersFor(Ticket $ticket): array
    {
        $ticket->loadMissing(['project', 'client', 'raiser.teams']);
        $raiser = $ticket->raiser;

        $ids = collect([$ticket->project?->manager_id, $raiser?->reports_to])
            ->merge($raiser?->teams->pluck('lead_id') ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $accountManagerUserId = $ticket->client?->account_manager_id;

        return Employee::query()
            ->active()
            ->whereHas('user', fn (Builder $user) => $user->holdingRole('manager'))
            ->where(fn (Builder $theirs) => $theirs
                ->whereIn('id', $ids)
                ->orWhere('user_id', $accountManagerUserId ?? 0))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Who a ticket can be given from the triage form (owner, 2026-11-06): the
     * support team, the Technical Associates, and its own Managers. Never the
     * person who raised it. Whoever holds it now stays on the list.
     *
     * @return Collection<int, Employee>
     */
    public static function assignableFor(Ticket $ticket): Collection
    {
        $managerIds = self::managersFor($ticket);

        return Employee::query()
            ->with('user')
            ->active()
            ->where(fn (Builder $anyOf) => $anyOf
                ->whereHas('user', fn (Builder $user) => $user->holdingRole('support'))
                ->orWhereHas('designation', fn (Builder $designation) => self::technicalDesignation($designation))
                ->orWhereIn('id', $managerIds)
                ->when($ticket->assignee_id !== null, fn (Builder $q) => $q->orWhere('id', $ticket->assignee_id)))
            ->when($ticket->raised_by !== null, fn (Builder $q) => $q->whereKeyNot($ticket->raised_by))
            ->get()
            ->sortBy(fn (Employee $employee) => (string) $employee->user?->name)
            ->values();
    }

    /**
     * Who an escalation may go to: anybody the ticket could be assigned to, the
     * escalating person's own manager and team leads, the CEO and the System
     * Administrators. Never the raiser, and never the person escalating.
     *
     * @return Collection<int, Employee>
     */
    public static function escalationTargets(Ticket $ticket, Employee $escalator): Collection
    {
        $escalator->loadMissing('teams');

        $seniorIds = collect([$escalator->reports_to])
            ->merge($escalator->teams->pluck('lead_id'))
            ->merge(self::holdersOf('ceo'))
            ->merge(self::holdersOf('system_admin'))
            ->filter()
            ->all();

        $ids = self::assignableFor($ticket)->pluck('id')->merge($seniorIds)->unique()->all();

        return Employee::query()
            ->with('user')
            ->active()
            ->whereIn('id', $ids)
            ->whereKeyNot($escalator->id)
            ->when($ticket->raised_by !== null, fn (Builder $q) => $q->whereKeyNot($ticket->raised_by))
            ->get()
            ->sortBy(fn (Employee $employee) => (string) $employee->user?->name)
            ->values();
    }

    /**
     * Where the system suggests an escalation goes. Support may change it.
     *
     * From Support: an IT matter to a Technical Associate, then the project's
     * Manager, the client's account manager, the raiser's manager or team lead.
     * From anybody else: their own manager, then their team lead. Either way the
     * CEO next, and a System Administrator when the CEO raised it.
     */
    public static function suggestedEscalation(Ticket $ticket, Employee $escalator): ?Employee
    {
        $targets = self::escalationTargets($ticket, $escalator)->keyBy('id');
        $escalator->loadMissing(['user', 'teams']);
        $ticket->loadMissing(['project', 'client', 'raiser.teams']);

        $fromSupport = $escalator->user !== null
            && User::whereKey($escalator->user_id)->holdingRole('support')->exists();

        $candidates = $fromSupport
            ? [
                in_array($ticket->category, (array) config('tickets.technical_categories', []), true)
                    ? TicketDirectory::technicalAgent()?->id
                    : null,
                $ticket->project?->manager_id,
                $ticket->client?->account_manager_id === null
                    ? null
                    : Employee::where('user_id', $ticket->client->account_manager_id)->value('id'),
                $ticket->raiser?->reports_to,
                ...($ticket->raiser?->teams->pluck('lead_id')->all() ?? []),
            ]
            : [
                $escalator->reports_to,
                ...$escalator->teams->pluck('lead_id')->all(),
            ];

        $candidates = [...$candidates, ...self::holdersOf('ceo'), ...self::holdersOf('system_admin')];

        foreach ($candidates as $id) {
            if ($id !== null && $targets->has((int) $id)) {
                return $targets->get((int) $id);
            }
        }

        return null;
    }

    /**
     * @return list<int> employee ids of active holders of a role
     */
    protected static function holdersOf(string $roleKey): array
    {
        return Employee::query()
            ->active()
            ->whereHas('user', fn (Builder $user) => $user->holdingRole($roleKey))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  Builder<MasterDataItem>  $designation
     * @return Builder<MasterDataItem>
     */
    protected static function technicalDesignation(Builder $designation): Builder
    {
        return $designation
            ->where('list', MasterDataItem::DESIGNATIONS)
            ->where('code', config('tickets.technical_designation_code'));
    }
}
