<?php

namespace Tests\Feature;

use App\Support\AnnouncementPresenter as P;
use App\Support\Demo\DemoAnnouncements;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoNotifications;
use App\Support\Milestones;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnouncementsPageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` now (§3.1), so
         * a page test has to be somebody. A CEO, because this file is about
         * what the page renders rather than about who may see it — the guard
         * and the permission filtering have their own tests.
         */
        $this->signInAsStaff();
    }
    /**
     * Announcements still come from DemoAnnouncements, which needs the
     * environment flip. The birthday and anniversary rails do not: they go
     * through Milestones, which reads the `employees` table now — so the people
     * have to be seeded as well as the environment flipped.
     */
    protected function withDemoData(): void
    {
        // The people, as rows — the birthday and anniversary rails go through
        // Milestones, which reads the `employees` table now.
        $this->seedDemoWorkforce();

        // And then the environment stays flipped, because the announcements
        // themselves are still DemoAnnouncements and gate on it. This module
        // has no table yet; when it gets one, this line goes.
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_the_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/announcements')->assertOk()->assertSee('Announcements', false);
        $this->get('/announcements/manage')->assertOk()->assertSee('Manage announcements', false);
        $this->get('/announcements/compose')->assertOk()->assertSee('Post an announcement', false);
        $this->get('/announcements/ANN-2026-036')->assertOk()->assertSee('Office closed', false);
        $this->get('/notifications')->assertOk()->assertSee('Things addressed to you', false);
    }

    public function test_manage_and_compose_are_not_read_as_announcement_references(): void
    {
        $this->withDemoData();

        $this->get('/announcements/manage')->assertOk();
        $this->get('/announcements/compose')->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ANNOUNCEMENTS ARE NOT NOTIFICATIONS

       Two surfaces (decided 2026-08-28). A board flooded by task events is a
       board nobody reads, and that is when "office closed Friday" gets missed.
       ══════════════════════════════════════════════════════════════════════ */

    public function test_task_and_ticket_notifications_never_reach_the_board(): void
    {
        $this->withDemoData();

        $board = $this->pageBody('/announcements');

        foreach (DemoNotifications::for(DemoNotifications::VIEWER) as $notification) {
            $this->assertStringNotContainsString($notification['title'], $board, 'a personal notification reached the board');
        }
    }

    public function test_the_board_never_carries_somebody_elses_notifications(): void
    {
        $this->withDemoData();

        $board = $this->get('/announcements')->getContent();

        // EMP004 has one in the sample data.
        $this->assertStringNotContainsString('Review the payment gateway logs', $board);
    }

    public function test_a_notification_belongs_to_one_reader(): void
    {
        // There is deliberately no `all()`: fetching somebody else's queue is
        // not something a forgotten where clause can cause.
        $this->withDemoData();

        $this->assertFalse(method_exists(DemoNotifications::class, 'all'));

        foreach (DemoNotifications::for('EMP002') as $notification) {
            $this->assertSame('EMP002', $notification['user']);
        }

        $mine = DemoNotifications::for('EMP002')->pluck('title');
        $theirs = DemoNotifications::for('EMP004')->pluck('title');

        $this->assertNotEmpty($theirs);
        $this->assertEmpty($mine->intersect($theirs));
    }

    public function test_the_notification_page_shows_only_the_viewers_own(): void
    {
        $this->withDemoData();

        $html = $this->get('/notifications')->getContent();

        foreach (DemoNotifications::for('EMP004') as $notification) {
            $this->assertStringNotContainsString($notification['title'], $html);
        }
    }

    public function test_a_notification_only_links_somewhere_that_exists(): void
    {
        // One aimed at a page that has not been built is a promise the
        // application cannot keep, and it fails when somebody acts on it.
        $this->withDemoData();

        foreach (DemoNotifications::for(DemoNotifications::VIEWER) as $notification) {
            if ($notification['link'] === null) {
                continue;
            }

            $this->assertStringStartsWith('http', $notification['link']);
            $this->assertTrue(app('router')->has($notification['route']));
        }
    }

    public function test_the_bell_is_fed_from_the_viewers_own_notifications(): void
    {
        $this->withDemoData();

        // The badge counts unread, and the panel lists them.
        $unread = DemoNotifications::unreadFor(DemoNotifications::VIEWER)->count();

        $this->assertGreaterThan(0, $unread);
        $this->get('/announcements')->assertSee('class="badge"', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       MILESTONES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_birth_year_appears_on_any_page(): void
    {
        // Date of birth is stored in full; the year never reaches a page.
        $this->withDemoData();

        // A bare four-digit search is useless here: "2000" matches
        // `w3.org/2000/svg` in every inline icon. What matters is the birth
        // DATE never appearing — raw, or formatted the way this app formats
        // dates elsewhere.
        $forbidden = DemoEmployees::all()
            ->pluck('dob')
            ->filter()
            ->flatMap(fn (string $dob) => [
                $dob,
                Carbon::parse($dob)->format('d M Y'),
                Carbon::parse($dob)->format('d/m/Y'),
                Carbon::parse($dob)->format('d M').' '.Carbon::parse($dob)->format('Y'),
            ])
            ->unique();

        $this->assertNotEmpty($forbidden);

        foreach (['/announcements', '/announcements/manage', '/employees'] as $url) {
            $html = $this->get($url)->getContent();

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $html, "a birth date ({$needle}) appears on {$url}");
            }
        }
    }

    public function test_the_birthday_rail_shows_a_day_and_month_and_no_year(): void
    {
        $this->withDemoData();

        $birthdays = DemoEmployees::birthdays();

        $this->assertNotEmpty($birthdays, 'no birthday in the window to review');

        foreach ($birthdays as $birthday) {
            // "29 Aug", not "29 Aug 1994".
            $this->assertMatchesRegularExpression('/^\d{2} [A-Z][a-z]{2}$/', $birthday['date']);
            $this->assertArrayHasKey('countdown', $birthday);
        }

        $this->get('/employees')->assertOk();
    }

    public function test_the_board_shows_upcoming_birthdays_and_anniversaries(): void
    {
        $this->withDemoData();

        $upcoming = Milestones::upcoming();

        $this->assertNotEmpty($upcoming, 'no milestones in the window to review');

        $html = $this->get('/announcements')->getContent();
        $this->assertStringContainsString('Coming up', $html);
        $this->assertStringContainsString($upcoming[0]['employee']['name'], $html);
    }

    public function test_somebody_who_opted_out_appears_nowhere_on_the_board(): void
    {
        $this->withDemoData();

        $optedOut = DemoEmployees::all()->first(fn (array $e) => ($e['announce_milestones'] ?? true) === false);

        $this->assertNotNull($optedOut, 'no opted-out employee to prove the rule against');

        foreach (Milestones::upcoming(null, 400) as $milestone) {
            $this->assertNotSame($optedOut['user_id'], $milestone['employee']['user_id']);
        }
    }

    public function test_milestones_are_never_listed_as_manageable(): void
    {
        // A computed post has nothing to edit, schedule or delete. Listing one
        // would offer actions that cannot work.
        $this->withDemoData();

        $manage = $this->get('/announcements/manage')->getContent();

        foreach (DemoAnnouncements::milestones() as $milestone) {
            $this->assertStringNotContainsString($milestone['id'], $manage);
        }

        // Whitespace-normalised: a sentence in a Blade template wraps across
        // lines, so matching raw markup tests the indentation rather than the
        // words.
        $this->assertStringContainsString(
            'computed from employee records',
            preg_replace('/\s+/', ' ', $manage)
        );
    }

    public function test_milestone_is_not_a_category_anyone_can_write(): void
    {
        // A hand-written one would look identical in the feed and be wrong the
        // following year.
        $this->withDemoData();

        $this->assertFalse(P::isAuthorable('milestone'));
        $this->assertArrayNotHasKey('milestone', P::authorableCategories());

        $compose = $this->get('/announcements/compose')->getContent();
        $this->assertStringNotContainsString('value="milestone"', $compose);
    }

    /* ══════════════════════════════════════════════════════════════════════
       STATE AND COUNTS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_status_is_derived_from_the_dates_and_the_draft_flag(): void
    {
        $this->withDemoData();

        foreach (DemoAnnouncements::authored() as $announcement) {
            $expected = match (true) {
                $announcement['draft'] => P::DRAFT,
                Carbon::parse($announcement['published_at'])->isFuture() => P::SCHEDULED,
                $announcement['expires_at'] !== null
                    && Carbon::parse($announcement['expires_at'])->endOfDay()->isPast() => P::EXPIRED,
                default => P::ACTIVE,
            };

            $this->assertSame($expected, $announcement['status'], "{$announcement['id']} has the wrong status");
        }
    }

    public function test_a_draft_is_never_on_the_board(): void
    {
        $this->withDemoData();

        $board = DemoAnnouncements::board()->pluck('id');
        $drafts = DemoAnnouncements::authored()->where('status', P::DRAFT);

        $this->assertNotEmpty($drafts, 'no draft in the sample data');

        foreach ($drafts as $draft) {
            $this->assertNotContains($draft['id'], $board);
            $this->assertStringNotContainsString($draft['title'], $this->get('/announcements')->getContent());
        }
    }

    public function test_a_scheduled_announcement_is_not_on_the_board_yet(): void
    {
        $this->withDemoData();

        $board = DemoAnnouncements::board()->pluck('id');

        foreach (DemoAnnouncements::authored()->where('status', P::SCHEDULED) as $item) {
            $this->assertNotContains($item['id'], $board);
        }
    }

    public function test_category_counts_are_computed_from_the_board(): void
    {
        // The handover wrote them in (7 / 6 / 8 / 5 / 4 / 6). A count that
        // cannot disagree with its own list is one nobody has to check.
        $this->withDemoData();

        $board = DemoAnnouncements::board();

        foreach (DemoAnnouncements::categoryCounts() as $row) {
            $this->assertSame($board->where('category', $row['key'])->count(), $row['count']);
            $this->assertGreaterThan(0, $row['count'], 'an empty category is listed as a filter that does nothing');
        }
    }

    public function test_the_category_filter_works(): void
    {
        $this->withDemoData();

        $response = $this->get('/announcements?category=policy');
        $response->assertSee('New laptop policy', false);
        $response->assertDontSee('Advanced Excel training', false);
    }

    public function test_an_invalid_category_or_tab_is_rejected(): void
    {
        $this->get('/announcements?category=gossip')->assertSessionHasErrors('category');
        $this->get('/announcements/manage?tab=whatever')->assertSessionHasErrors('tab');
        $this->get('/notifications?tab=whatever')->assertSessionHasErrors('tab');
    }

    public function test_an_expired_announcement_is_kept_and_says_so(): void
    {
        // What the company said and when is worth being able to look up.
        $this->withDemoData();

        $expired = DemoAnnouncements::authored()->firstWhere('status', P::EXPIRED);

        $this->assertNotNull($expired);
        $this->get('/announcements/'.$expired['id'])->assertSee('come off the board', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_sources_are_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertTrue(DemoAnnouncements::authored()->isEmpty());
        $this->assertTrue(DemoAnnouncements::milestones()->isEmpty());
        $this->assertTrue(DemoNotifications::for('EMP002')->isEmpty());
        $this->assertSame([], Milestones::upcoming());
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 36 / 12 / 4 / 3.
        $this->withDemoData();

        $response = $this->get('/announcements/manage');
        $response->assertSee('Drafts', false);
        $response->assertDontSee('>36<', false);
    }

    public function test_an_unknown_announcement_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/announcements/ANN-9999-999')->assertNotFound();
        $this->get('/announcements/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        $this->assertTrue(app('router')->has('announcements.store'));
        $this->assertTrue(app('router')->has('announcements.publish'));
        $this->assertTrue(app('router')->has('announcements.expire'));
        $this->assertTrue(app('router')->has('notifications.read'));
    }

    public function test_nothing_creates_a_milestone(): void
    {
        // They are computed on every request. A stored one would be wrong the
        // following year and would survive somebody opting out.
        $this->assertFalse(app('router')->has('announcements.milestones.store'));

        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'announcements')) {
                $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            }
        }
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        // The handover wired its tabs with an inline <script>.
        $this->withDemoData();

        foreach (['/announcements', '/announcements/manage', '/announcements/compose', '/announcements/ANN-2026-036', '/notifications'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_the_right_entry(): void
    {
        $this->withDemoData();

        foreach (['/announcements', '/announcements/manage', '/announcements/compose'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
