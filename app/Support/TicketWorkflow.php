<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Rbac\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Who sees a ticket, who may act on it, and where it goes next.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PROCESS (owner, 2026-11-06 and 2026-11-07). ROLES DECIDE, NOT DESIGNATIONS.
 *
 * Everything lands with Support (a client's technical issue goes straight to a
 * System Administrator). Support solves it, assigns it, or escalates it.
 *
 * - Support and the CEO: every ticket. Resolve directly, assign to a Manager of
 *   the ticket (any Manager when it has none), a System Administrator when it is
 *   an IT ticket, or any team. Never to another Support person.
 * - A Manager: their projects', clients' and people's tickets. Resolve directly,
 *   assign to people in their own teams and projects, or one of those teams.
 * - A System Administrator: IT tickets only (Access, Network, Bug). Resolve
 *   directly, assign to another System Administrator or a team.
 * - A team given a ticket: every member sees it and may resolve it; the lead may
 *   give it to one member.
 * - Whoever holds a ticket resolves it or escalates it to their seniors.
 *
 * There is no "take it myself": whoever may resolve a ticket resolves it, and
 * the ticket says who did.
 *
 * NOBODY DECIDES THEIR OWN TICKET
 *
 * Whoever raised a ticket may reply and attach files, and nothing else, and is
 * never offered as its assignee. The CEO included.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class TicketWorkflow
{
    /** Every ticket. Support, the CEO, Mentors (read only). */
    public const VIEW_ALL = 'tickets.view.all';

    /** Routing, assigning and resolving. Support, Managers and the CEO. */
    public const TRIAGE = 'tickets.triage';

    /** IT tickets, and only those. The System Administrator (owner, 2026-11-07). */
    public const IT = 'tickets.it';

    protected static ?bool $ready = null;

    /**
     * False between uploading this version and applying its migration, when the
     * ticket's team and resolver columns do not exist yet.
     */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasColumn('tickets', 'team_id');
    }

    public static function employeeFor(?User $user): ?Employee
    {
        return $user === null ? null : Employee::where('user_id', $user->id)->first();
    }

    public static function isIt(Ticket $ticket): bool
    {
        return in_array($ticket->category, self::itCategories(), true);
    }

    /**
     * @return list<string>
     */
    public static function itCategories(): array
    {
        return (array) config('tickets.technical_categories', []);
    }

    /**
     * The tickets this person may see.
     *
     * Everybody sees what they raised, hold, escalated or resolved, what was given
     * to a team they are in, and their own clients'. A System Administrator also
     * sees every IT ticket. A Manager (anybody who triages) also sees their
     * projects', and those of people who report to them or are in a team they lead.
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

        $employee = self::employeeFor($user);
        $employeeId = $employee?->id ?? 0;
        $triages = $rbac->can($user, self::TRIAGE);
        $handlesIt = $rbac->can($user, self::IT);
        $ready = self::ready();
        $teamIds = $ready ? self::teamIdsOf($employee) : [];

        return $query->where(fn (Builder $mine) => $mine
            ->where('raised_by', $employeeId)
            ->orWhere('assignee_id', $employeeId)
            ->orWhere('escalated_by', $employeeId)
            ->when($ready, fn (Builder $q) => $q
                ->orWhere('resolved_by', $employeeId)
                ->orWhereIn('team_id', $teamIds))
            ->orWhereHas('client', fn (Builder $client) => $client->where('account_manager_id', $user->id))
            ->when($handlesIt, fn (Builder $q) => $q->orWhereIn('category', self::itCategories()))
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
     * Assigning, escalating and resolving all start here: never on one's own
     * ticket, and never on a closed one.
     */
    public static function mayDecide(Ticket $ticket, ?User $user): bool
    {
        return $ticket->status !== 'closed' && ! self::isOwn($ticket, self::employeeFor($user));
    }

    /**
     * May set who handles it, its priority and category: Support, a Manager and
     * the CEO; a System Administrator on an IT ticket; the lead of the team it was
     * given to.
     */
    public static function mayTriage(Ticket $ticket, ?User $user): bool
    {
        if (! self::mayDecide($ticket, $user)) {
            return false;
        }

        return app(Rbac::class)->can($user, self::TRIAGE)
            || self::handlesAsIt($ticket, $user)
            || self::leadsTeamOf($ticket, self::employeeFor($user));
    }

    /**
     * May resolve or escalate it: Support, a Manager and the CEO; a System
     * Administrator on an IT ticket; whoever holds it; anybody in the team it was
     * given to.
     */
    public static function mayWorkOn(Ticket $ticket, ?User $user): bool
    {
        if (! self::mayDecide($ticket, $user) || $ticket->status === 'resolved') {
            return false;
        }

        $employee = self::employeeFor($user);

        return app(Rbac::class)->can($user, self::TRIAGE)
            || self::handlesAsIt($ticket, $user)
            || ($employee !== null && $ticket->assignee_id === $employee->id)
            || self::inTeamOf($ticket, $employee);
    }

    public static function handlesAsIt(Ticket $ticket, ?User $user): bool
    {
        return self::isIt($ticket) && app(Rbac::class)->can($user, self::IT);
    }

    /**
     * Support's desk: sees every ticket and routes it. Support and the CEO.
     */
    public static function isFrontDesk(?User $user): bool
    {
        $rbac = app(Rbac::class);

        return $rbac->can($user, self::TRIAGE) && $rbac->can($user, self::VIEW_ALL);
    }

    public static function inTeamOf(Ticket $ticket, ?Employee $employee): bool
    {
        return self::ready() && $ticket->team_id !== null && in_array($ticket->team_id, self::teamIdsOf($employee), true);
    }

    public static function leadsTeamOf(Ticket $ticket, ?Employee $employee): bool
    {
        return $employee !== null && self::ready() && $ticket->team_id !== null
            && Team::whereKey($ticket->team_id)->where('lead_id', $employee->id)->exists();
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
     * Who, and which teams, this person may give the ticket to. Never the person
     * who raised it, never themselves. Whoever holds it now stays on the list, so
     * the form never drops them.
     *
     * @return array{people: Collection<int, Employee>, teams: Collection<int, Team>}
     */
    public static function assignOptions(Ticket $ticket, ?User $user): array
    {
        $employee = self::employeeFor($user);
        $people = collect();
        $teamIds = collect();
        $everyTeam = false;

        if (self::isFrontDesk($user)) {
            $managers = self::managersFor($ticket);
            $people = $people->merge($managers === [] ? self::holdersOf('manager') : $managers);
            $everyTeam = true;

            if (self::isIt($ticket)) {
                $people = $people->merge(self::holdersOf('system_admin'));
            }
        }

        if ($employee !== null && $user !== null && app(Rbac::class)->can($user, self::TRIAGE)
            && User::whereKey($user->id)->holdingRole('manager')->exists()) {
            $theirTeams = Team::query()->active()
                ->where(fn (Builder $q) => $q
                    ->where('lead_id', $employee->id)
                    ->orWhereHas('projects', fn (Builder $project) => $project->where('manager_id', $employee->id)))
                ->with('members')
                ->get();

            $teamIds = $teamIds->merge($theirTeams->pluck('id'));
            $people = $people->merge($theirTeams->flatMap(fn (Team $team) => $team->members->pluck('id')))
                ->merge($theirTeams->pluck('lead_id'));
        }

        if (self::handlesAsIt($ticket, $user)) {
            $people = $people->merge(self::holdersOf('system_admin'));
            $everyTeam = true;
        }

        if (self::leadsTeamOf($ticket, $employee)) {
            $people = $people->merge(Team::find($ticket->team_id)?->members->pluck('id') ?? []);
        }

        $people = $people->filter()->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $employee?->id || $id === $ticket->raised_by)
            ->when($ticket->assignee_id !== null, fn (Collection $ids) => $ids->push($ticket->assignee_id))
            ->unique();

        if (self::ready() && $ticket->team_id !== null) {
            $teamIds->push($ticket->team_id);
        }

        return [
            'people' => Employee::query()->with('user')->active()->whereIn('id', $people->all())->get()
                ->sortBy(fn (Employee $e) => (string) $e->user?->name)->values(),
            'teams' => ! self::ready() ? collect() : Team::query()
                ->when(! $everyTeam, fn (Builder $q) => $q->whereIn('id', $teamIds->unique()->all()))
                ->where(fn (Builder $q) => $q->active()->orWhere('id', $ticket->team_id ?? 0))
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Who an escalation may go to: the ticket's Managers, a System Administrator
     * for an IT ticket, the escalating person's own manager and team leads, and
     * the CEO. Never the raiser, and never the person escalating.
     *
     * @return Collection<int, Employee>
     */
    public static function escalationTargets(Ticket $ticket, Employee $escalator): Collection
    {
        $escalator->loadMissing('teams');

        $ids = collect(self::managersFor($ticket))
            ->merge(self::isIt($ticket) ? self::holdersOf('system_admin') : [])
            ->merge([$escalator->reports_to])
            ->merge($escalator->teams->pluck('lead_id'))
            ->merge(self::holdersOf('ceo'))
            ->filter()
            ->unique()
            ->all();

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
     * Where the system suggests an escalation goes. The person escalating may
     * change it.
     *
     * From Support: an IT ticket to a System Administrator, otherwise the
     * project's Manager, the client's account manager, the raiser's manager or
     * team lead. From anybody else: their own manager, then their team lead.
     * Either way the CEO next. The CEO's own ticket that is not IT has no
     * suggestion: it stays with Support, who assign it (owner, 2026-11-07).
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
                self::isIt($ticket) ? TicketDirectory::technicalAgent()?->id : null,
                ...(self::isIt($ticket) ? self::holdersOf('system_admin') : []),
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

        foreach ([...$candidates, ...self::holdersOf('ceo')] as $id) {
            if ($id !== null && $targets->has((int) $id)) {
                return $targets->get((int) $id);
            }
        }

        return null;
    }

    /**
     * The teams somebody leads or is in.
     *
     * @return list<int>
     */
    public static function teamIdsOf(?Employee $employee): array
    {
        if ($employee === null) {
            return [];
        }

        return Team::query()->linkedTo($employee)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<int> employee ids of active holders of a role
     */
    public static function holdersOf(string $roleKey): array
    {
        return Employee::query()
            ->active()
            ->whereHas('user', fn (Builder $user) => $user->holdingRole($roleKey))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
