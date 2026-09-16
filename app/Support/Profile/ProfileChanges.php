<?php

namespace App\Support\Profile;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use App\Support\ProfilePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Correcting somebody's own details, through HR (2026-09-14).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE PLACE THAT APPLIES A REQUEST, AND IT IS THIS ONE
 *
 * Applying is three things at once: a write to the live record, a photo file
 * moving from candidate to current, and the request being marked spent. A
 * second call site doing two of the three is how a photo gets accepted while
 * the address does not, and nothing afterwards can tell which half happened.
 * So it is one method, in a transaction, and the controller asks it rather than
 * doing any of it.
 *
 * ONLY WHAT DIFFERS IS STORED
 *
 * A form posts eleven fields and somebody changed one. Storing all eleven would
 * give HR a list of ten things to read that say nothing, and would make the
 * request look like a rewrite of the record when it is a single correction. So
 * `propose()` compares against the live row and keeps the differences.
 *
 * Which also means an empty request is not written at all. Somebody who opens
 * the form, changes nothing and presses the button has not asked for anything,
 * and a pending row saying so would sit in HR's queue forever.
 *
 * THE FIELD LIST IS CHECKED TWICE
 *
 * On the way in, and again on the way out. The value in `changes` is a field
 * NAME that will later be used to write to the column it names — the same shape
 * as the identifier reveal, and the same treatment: never trusted, always
 * checked against ProfilePolicy::requestable(). A request stored before a field
 * left that list is a request that must not apply it afterwards.
 *
 * NOTHING IS DELETED, EXCEPT A PHOTO NOBODY WANTED
 *
 * Spent requests are kept: "they asked and it was declined" is the history an
 * argument turns on later. The one exception is the candidate photo file on a
 * declined or withdrawn request — a photograph nobody accepted is not a record
 * of anything, and keeping every one of them grows the disk forever.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProfileChanges
{
    public function __construct(protected DocumentStore $documents)
    {
    }

    /**
     * The request waiting on HR for this person, if there is one.
     */
    public function pendingFor(Employee $employee): ?ProfileChangeRequest
    {
        return ProfileChangeRequest::query()
            ->pending()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Everything waiting on HR, oldest first.
     *
     * Oldest first on purpose: a queue that showed the newest at the top would
     * bury the request somebody has been waiting three weeks for underneath the
     * one submitted this morning.
     *
     * @return Collection<int, ProfileChangeRequest>
     */
    public function queue(): Collection
    {
        return ProfileChangeRequest::query()
            ->pending()
            ->with(['employee.user', 'requester'])
            ->orderBy('id')
            ->get();
    }

    /**
     * What this person has asked for before, newest first.
     *
     * @return Collection<int, ProfileChangeRequest>
     */
    public function historyFor(Employee $employee, int $limit = 10): Collection
    {
        return ProfileChangeRequest::query()
            ->where('employee_id', $employee->id)
            // Spent, by any of the three routes. Grouped so the OR cannot leak
            // out and widen the employee filter beside it.
            ->where(fn ($q) => $q
                ->whereNotNull('applied_at')
                ->orWhereNotNull('rejected_at')
                ->orWhereNotNull('cancelled_at'))
            ->with('decider')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Work out what actually differs from the record.
     *
     * @param  array<string, mixed>  $submitted  validated, keyed by field
     * @return array<string, mixed>
     */
    public function diff(Employee $employee, array $submitted): array
    {
        $current = $employee->profile?->toRecordArray() ?? EmployeeProfile::blank();

        $changes = [];

        foreach ($submitted as $field => $value) {
            // Checked here as well as by the validator: this is the list, and
            // the list is checked wherever a field name crosses a boundary.
            if (! ProfilePolicy::isRequestable($field)) {
                continue;
            }

            $was = $current[$field] ?? null;

            // Loose-ish comparison on purpose: a list arrives as an array and
            // an empty string and a null both mean "nothing stated", so
            // clearing an already-empty field is not a change somebody made.
            if ($this->same($was, $value)) {
                continue;
            }

            $changes[$field] = $value;
        }

        return $changes;
    }

    /**
     * Write the request. Returns null when nothing was actually asked for.
     *
     * @param  array<string, mixed>  $changes  already diffed
     */
    public function propose(
        Employee $employee,
        array $changes,
        User $by,
        ?string $photoPath = null,
    ): ?ProfileChangeRequest {
        if ($changes === [] && $photoPath === null) {
            return null;
        }

        return ProfileChangeRequest::create([
            'employee_id' => $employee->id,
            'requested_by' => $by->id,
            'changes' => $changes,
            'photo_path' => $photoPath,
        ]);
    }

    /**
     * Move the record, and mark the request applied.
     *
     * Returns the labels of what moved, for the audit entry and the message —
     * never the values. Where somebody lives is not something to copy into the
     * one table nobody may edit.
     *
     * @return list<string>
     */
    public function apply(ProfileChangeRequest $request, User $by): array
    {
        if (! $request->isPending()) {
            return [];
        }

        return DB::transaction(function () use ($request, $by): array {
            $employee = $request->employee;

            $profile = $employee->profile
                ?? EmployeeProfile::create(['employee_id' => $employee->id]);

            $applied = [];
            $fill = [];

            foreach ((array) $request->changes as $field => $value) {
                // The second check. See the head of this class.
                if (! ProfilePolicy::isRequestable($field) || $field === 'photo') {
                    continue;
                }

                $fill[$field] = $value;
                $applied[] = ProfilePolicy::labelOf($field);
            }

            $wasPhoto = $profile->photo_path;

            if ($request->photo_path !== null) {
                $fill['photo_path'] = $request->photo_path;
                $applied[] = ProfilePolicy::labelOf('photo');
            }

            if ($fill !== []) {
                $profile->fill($fill)->save();
            }

            /*
             * The old photo goes only after the new path is saved — the same
             * order the direct upload used, and for the same reason: the other
             * way round leaves somebody with no photo at all if the write
             * fails.
             */
            if ($request->photo_path !== null && $wasPhoto !== null && $wasPhoto !== $request->photo_path) {
                $this->documents->forget($wasPhoto);
            }

            $request->forceFill([
                'applied_at' => now(),
                'decided_by' => $by->id,
            ])->save();

            return $applied;
        });
    }

    /**
     * HR says no, with a reason.
     */
    public function reject(ProfileChangeRequest $request, User $by, string $note): void
    {
        if (! $request->isPending()) {
            return;
        }

        $request->forceFill([
            'rejected_at' => now(),
            'decided_by' => $by->id,
            'decision_note' => $note,
        ])->save();

        $this->discardPhoto($request);
    }

    /**
     * The person changes their mind before HR gets to it.
     */
    public function withdraw(ProfileChangeRequest $request): void
    {
        if (! $request->isPending()) {
            return;
        }

        $request->forceFill(['cancelled_at' => now()])->save();

        $this->discardPhoto($request);
    }

    /**
     * The labels of what a request asks for, for a queue that must not print
     * somebody's home address in a list.
     *
     * @return list<string>
     */
    public function summarise(ProfileChangeRequest $request): array
    {
        $labels = [];

        foreach (array_keys((array) $request->changes) as $field) {
            $labels[] = ProfilePolicy::labelOf($field);
        }

        if ($request->photo_path !== null) {
            $labels[] = ProfilePolicy::labelOf('photo');
        }

        return $labels;
    }

    /**
     * The before-and-after HR reads, one field at a time.
     *
     * Built here rather than in the view so the values are never assembled by a
     * template that might print one it was not given — and so "not stated" is a
     * single phrase rather than three spellings across three blades.
     *
     * @return list<array{label: string, was: string, now: string}>
     */
    public function comparison(ProfileChangeRequest $request): array
    {
        $current = $request->employee?->profile?->toRecordArray() ?? EmployeeProfile::blank();

        $rows = [];

        foreach ((array) $request->changes as $field => $value) {
            if (! ProfilePolicy::isRequestable($field)) {
                continue;
            }

            $rows[] = [
                'label' => ProfilePolicy::labelOf($field),
                'was' => $this->readable($current[$field] ?? null),
                'now' => $this->readable($value),
            ];
        }

        return $rows;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * A candidate photo is only worth keeping while somebody might accept it.
     */
    protected function discardPhoto(ProfileChangeRequest $request): void
    {
        if ($request->photo_path !== null) {
            $this->documents->forget($request->photo_path);
        }
    }

    protected function same(mixed $was, mixed $now): bool
    {
        if (is_array($was) || is_array($now)) {
            return (array) $was === (array) $now;
        }

        // '' and null both mean nothing stated, and neither is a change from
        // the other.
        return (string) ($was ?? '') === (string) ($now ?? '');
    }

    protected function readable(mixed $value): string
    {
        if (is_array($value)) {
            return $value === [] ? 'Not stated' : implode(', ', $value);
        }

        $value = trim((string) ($value ?? ''));

        return $value === '' ? 'Not stated' : $value;
    }
}
