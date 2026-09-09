<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Teams, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ROW SHAPE IS THE ONE THE VIEWS ALREADY READ
 *
 * `id`, `name`, `purpose`, `status`, `created`, plus the `lead_record` and
 * `member_count` the table draws. Same contract as ClientDirectory and
 * EmployeeDirectory: the templates and their tests were working, so the
 * data-source change does not touch them.
 *
 * `id` in that shape is the REFERENCE — TM-1001, what the URL carries — and not
 * the primary key. The views were built against it and it is what people quote.
 *
 * WHY member_count IS A SUBQUERY AND NOT count($team['members'])
 *
 * The list draws ten teams. Loading every member of every one to count them is
 * ten extra queries and a few hundred rows to display ten numbers; the count
 * comes back with the team instead.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class TeamDirectory
{
    /**
     * The list query, filtered.
     *
     * @return Builder<Team>
     */
    public static function query(?string $search = null, ?string $status = null, ?string $lead = null): Builder
    {
        return Team::query()
            ->with(['lead.user', 'lead.designation'])
            ->withCount('members')
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('name', 'like', $like)
                    ->orWhere('purpose', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            }))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            /*
             * The lead filter takes a STAFF ID, because that is what the
             * dropdown offers and what a shared URL should be readable as. A
             * primary key in a query string is neither.
             */
            ->when($lead, fn (Builder $q, string $staffId) => $q->whereHas(
                'lead.user',
                fn (Builder $u) => $u->where('user_id', $staffId)
            ))
            ->orderBy('name');
    }

    /**
     * One team in the shape the views read.
     *
     * @return array<string, mixed>
     */
    public static function row(Team $team): array
    {
        return [
            'id' => $team->reference,
            'name' => $team->name,
            'purpose' => $team->purpose,
            'status' => $team->status,
            'created' => $team->formed_on?->toDateString() ?? $team->created_at?->toDateString(),
            'lead' => $team->lead?->user?->user_id,
            'lead_record' => $team->lead ? EmployeeDirectory::row($team->lead) : null,
            /*
             * `members_count` is present when the query asked for it and absent
             * on a record loaded some other way, so the relation is the
             * fallback rather than the assumption.
             */
            'member_count' => $team->members_count ?? $team->members()->count(),
        ];
    }

    /**
     * The members of one team, as employee rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function membersOf(Team $team): array
    {
        return $team->members()
            ->with(['user', 'department', 'designation'])
            ->get()
            ->map(fn (Employee $e) => EmployeeDirectory::row($e))
            ->all();
    }

    /**
     * The headline counts.
     *
     * @param  Builder<Team>|null  $scope  count within one list rather than all
     * @return array<string, int>
     */
    public static function stats(?Builder $scope = null): array
    {
        $count = fn (?string $status = null) => (clone ($scope ?? Team::query()))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->count();

        return [
            'total' => $count(),
            'active' => $count('active'),
            'inactive' => $count('inactive'),
        ];
    }

    /**
     * The leads a filter may offer — the people actually leading something.
     *
     * Not every employee: a filter that returns nothing is a dead end somebody
     * has to discover by trying it.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function leadOptions(): array
    {
        return Employee::query()
            ->with('user')
            ->whereIn('id', Team::query()->whereNotNull('lead_id')->select('lead_id'))
            ->get()
            ->map(fn (Employee $e) => ['id' => (string) $e->user?->user_id, 'name' => (string) $e->user?->name])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Recent changes to teams, from the audit log (§6).
     *
     * What was actually recorded and by whom. The demo source invented five
     * plausible sentences, which is the kind of thing somebody eventually
     * quotes back at you in a meeting.
     *
     * @return list<array<string, string>>
     */
    public static function activity(int $limit = 5): array
    {
        return DB::table('audit_log')
            ->where('entity_type', 'team')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'what' => self::summaryOf($row) ?? self::phraseFor($row->action),
                'who' => $row->actor_label,
                'when' => Carbon::parse($row->created_at)->format('d M Y, g:i A'),
                'tone' => match ($row->action) {
                    'team.status_changed' => 'tone-warn',
                    'team.members_changed' => 'tone-accent',
                    default => '',
                },
            ])
            ->all();
    }

    protected static function summaryOf(object $row): ?string
    {
        $decoded = json_decode((string) $row->after_json, true);

        return is_array($decoded) ? ($decoded['summary'] ?? null) : null;
    }

    protected static function phraseFor(string $action): string
    {
        return match ($action) {
            'team.created' => 'A team was created',
            'team.updated' => 'A team was updated',
            'team.status_changed' => 'A team changed status',
            'team.members_changed' => 'A team\'s membership changed',
            default => $action,
        };
    }
}
