<?php

namespace Tests\Unit;

use App\Support\ProjectPresenter;
use App\Support\TicketPresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketPresenterTest extends TestCase
{
    public function test_every_status_has_its_own_words(): void
    {
        foreach (TicketPresenter::statusOptions() as $status) {
            $rendered = TicketPresenter::status($status);

            $this->assertNotSame('', $rendered['label']);
            $this->assertStringStartsWith('pill-', $rendered['tone']);
        }
    }

    public function test_an_unknown_status_is_shown_rather_than_swallowed(): void
    {
        // A status the presenter does not recognise still has to appear, or a
        // data problem becomes an invisible one.
        $rendered = TicketPresenter::status('awaiting_client');

        $this->assertSame('Awaiting client', $rendered['label']);
        $this->assertSame('pill-gray', $rendered['tone']);
    }

    public function test_priority_means_the_same_thing_as_it_does_on_a_project(): void
    {
        foreach (ProjectPresenter::priorityOptions() as $priority) {
            $this->assertSame(
                ProjectPresenter::priority($priority),
                TicketPresenter::priority($priority),
                "priority '{$priority}' reads differently on a ticket than on a project"
            );
        }

        $this->assertSame(ProjectPresenter::priorityOptions(), TicketPresenter::priorityOptions());
    }

    public function test_an_untriaged_priority_says_not_set(): void
    {
        $this->assertSame('Not set', TicketPresenter::priority(null)['label']);
        $this->assertSame('Not set', TicketPresenter::priority('')['label']);
    }

    public function test_orNotSet_covers_the_other_untriaged_fields(): void
    {
        $this->assertSame('Not set', TicketPresenter::orNotSet(null));
        $this->assertSame('Not set', TicketPresenter::orNotSet(''));
        $this->assertSame('Billing', TicketPresenter::orNotSet('Billing'));
    }

    /**
     * The one that matters.
     */
    public function test_a_comment_is_internal_unless_it_explicitly_says_otherwise(): void
    {
        foreach (self::secrecyCases() as $name => [$comment, $expected]) {
            $this->assertSame($expected, TicketPresenter::isInternal($comment), "case: {$name}");
        }
    }

    public static function secrecyCases(): array
    {
        return [
            'explicitly public' => [['visibility' => 'public'], false],
            'explicitly internal' => [['visibility' => 'internal'], true],
            'missing key' => [['body' => 'no visibility set'], true],
            'null' => [['visibility' => null], true],
            'empty string' => [['visibility' => ''], true],
            'typo' => [['visibility' => 'publik'], true],
            'wrong case' => [['visibility' => 'PUBLIC'], true],
            'padded' => [['visibility' => ' public '], true],
            'truthy but not the word' => [['visibility' => true], true],
        ];
    }

    public function test_types_read_as_words(): void
    {
        $this->assertSame('Client', TicketPresenter::typeLabel('client'));
        $this->assertSame('Internal', TicketPresenter::typeLabel('internal'));
        $this->assertSame(['internal', 'client'], TicketPresenter::typeOptions());
    }

    public function test_dates_and_times_are_formatted_the_way_the_rest_of_the_app_formats_them(): void
    {
        $when = Carbon::create(2026, 8, 27, 14, 5);

        $this->assertSame('27 Aug 2026', TicketPresenter::date($when));
        $this->assertSame('2:05 PM', TicketPresenter::time($when));
        $this->assertSame('27 Aug 2026', TicketPresenter::date('2026-08-27'));
    }
}
