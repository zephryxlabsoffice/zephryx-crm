<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Support\Demo\DemoAnnouncements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo board. Local + debug only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE TWO HOLIDAY NOTICES ARE THE REASON THIS SEEDER MATTERS
 *
 * One is a current closure and one is long past with an expired notice. Both
 * carry observed dates, and Attendance reads them — so the seed is what makes
 * "nobody was absent on the day the office was shut" reviewable, including for
 * a day whose notice has already come down.
 *
 * Milestones are not seeded and never will be: they are computed from employee
 * records on every request.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $departments = MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->pluck('id', 'name');

        foreach (DemoAnnouncements::authored() as $row) {
            $author = $employees->get($row['author']);

            if ($author === null) {
                continue;
            }

            $announcement = Announcement::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'category' => $row['category'],
                    'author_id' => $author->id,
                    'audience' => $row['audience'],
                    'audience_department_id' => $row['audience_value']
                        ? ($departments[$row['audience_value']] ?? null)
                        : null,
                    'for_clients' => $row['for_clients'] ?? false,
                    'starts_on' => Carbon::parse($row['published_at'])->toDateString(),
                    'ends_on' => $row['expires_at'],
                    // The days the office is shut — not the window the notice is
                    // up for. Null on everything that is not a closure.
                    'observed_from' => $row['observed_from'],
                    'observed_to' => $row['observed_to'],
                    // A draft closes nothing, so a drafted holiday stays unpublished.
                    'published_at' => $row['draft'] ? null : Carbon::parse($row['published_at']),
                    'pinned' => $row['pinned'],
                ],
            );

            $announcement->forceFill([
                'created_at' => Carbon::parse($row['published_at']),
            ])->saveQuietly();
        }
    }
}
