<?php

namespace Tests\Unit;

use App\Support\ProfilePolicy;
use App\Support\ProfilePresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProfilePolicyTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       OWNERSHIP
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_three_fields_the_handover_let_people_edit_are_not_theirs(): void
    {
        // Name, email and date of birth were editable text boxes in the
        // handover's form. Each is a different kind of wrong.
        $this->assertSame(ProfilePolicy::HR, ProfilePolicy::ownerOf('name'));
        $this->assertSame(ProfilePolicy::HR, ProfilePolicy::ownerOf('dob'));
        $this->assertSame(ProfilePolicy::VERIFIED, ProfilePolicy::ownerOf('email'));

        foreach (['name', 'dob', 'email'] as $field) {
            $this->assertFalse(ProfilePolicy::isSelfEditable($field));
        }
    }

    public function test_an_unrecognised_field_is_not_the_persons(): void
    {
        // The safe default for "who may change this" is somebody other than the
        // subject of it — a field added without a decision is locked, not open.
        $this->assertSame(ProfilePolicy::HR, ProfilePolicy::ownerOf('anything_at_all'));
    }

    public function test_the_allow_list_is_exactly_the_self_owned_fields(): void
    {
        // The write validates against this and drops everything else, so the two
        // must not be able to drift apart.
        foreach (ProfilePolicy::selfEditable() as $field) {
            $this->assertSame(ProfilePolicy::SELF, ProfilePolicy::ownerOf($field));
        }

        $this->assertSame(
            ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SELF),
            ProfilePolicy::selfEditable(),
        );
    }

    public function test_the_credentials_are_neither_the_persons_to_type_nor_hrs_to_set(): void
    {
        // A third state, and it is not a fudge: the email and password ARE the
        // person's, but through a flow that proves it rather than a text box.
        // HR setting somebody's password for them is its own bad idea.
        foreach (['email', 'password'] as $field) {
            $this->assertSame(ProfilePolicy::VERIFIED, ProfilePolicy::ownerOf($field));
            $this->assertFalse(ProfilePolicy::isSelfEditable($field));
            $this->assertNotContains($field, ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR));
        }
    }

    public function test_nobody_types_the_system_fields_including_hr(): void
    {
        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SYSTEM) as $field) {
            $this->assertFalse(ProfilePolicy::isSelfEditable($field));
            $this->assertNotContains($field, ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR));
        }

        $this->assertContains('employee_id', ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SYSTEM));
        $this->assertContains('last_login', ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SYSTEM));
    }

    public function test_every_locked_field_can_explain_itself(): void
    {
        // Shown next to each one. "Read-only" with no reason reads as the
        // application being unfinished, and the first thing somebody does about
        // it is ask — which is the support request the text exists to save.
        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field) {
            $this->assertNotSame('', ProfilePolicy::whyOf($field));
            $this->assertNotSame('', ProfilePolicy::labelOf($field));
        }
    }

    public function test_the_person_still_owns_a_useful_amount(): void
    {
        // Locking everything would be safe and useless. Nobody should raise a
        // ticket to correct their own phone number.
        $editable = ProfilePolicy::selfEditable();

        foreach (['phone', 'current_address', 'permanent_address', 'emergency_phone', 'skills', 'announce_milestones', 'theme'] as $field) {
            $this->assertContains($field, $editable);
        }
    }

    public function test_prefer_not_to_say_is_a_real_answer_and_comes_first(): void
    {
        // Somebody who does not want to state their gender must not have to pick
        // the least wrong option from a list they did not write.
        foreach (ProfilePolicy::options() as $field => $options) {
            $this->assertSame('Prefer not to say', $options[0], "{$field} does not offer it first");
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       PRESENTATION
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_birthday_is_a_day_and_a_month_never_the_year(): void
    {
        $this->assertSame('29 August', P::dayAndMonth('1994-08-29'));
        $this->assertStringNotContainsString('1994', P::dayAndMonth('1994-08-29'));
    }

    public function test_tenure_reads_the_way_a_person_would_say_it(): void
    {
        $this->assertSame('2 years', P::tenure(Carbon::today()->subYears(2)));
        $this->assertSame('1 year, 3 months', P::tenure(Carbon::today()->subYears(1)->subMonths(3)));
        $this->assertSame('5 months', P::tenure(Carbon::today()->subMonths(5)));
        $this->assertSame('1 month', P::tenure(Carbon::today()->subMonth()));
        $this->assertSame('Joined this month', P::tenure(Carbon::today()->subDays(3)));
    }

    public function test_a_future_start_date_is_not_negative_tenure(): void
    {
        $start = Carbon::today()->addDays(14);

        $this->assertSame('Starts '.$start->format('d M Y'), P::tenure($start));
    }

    public function test_password_age_reports_and_does_not_nag(): void
    {
        // §4.7: no forced rotation. This states a fact and stops.
        $this->assertSame('Changed today.', P::passwordAge(Carbon::today()));
        $this->assertSame('Changed yesterday.', P::passwordAge(Carbon::today()->subDay()));
        $this->assertSame('Changed 12 days ago.', P::passwordAge(Carbon::today()->subDays(12)));

        $old = P::passwordAge(Carbon::today()->subDays(400));
        $this->assertStringStartsWith('Changed on ', $old);
        $this->assertStringNotContainsStringIgnoringCase('should', $old);
        $this->assertStringNotContainsStringIgnoringCase('expire', $old);
    }

    public function test_a_never_changed_password_says_so_plainly(): void
    {
        $this->assertStringContainsString('Never changed', P::passwordAge(null));
    }

    public function test_file_sizes_are_readable(): void
    {
        $this->assertSame('512 B', P::fileSize(512));
        $this->assertSame('246 KB', P::fileSize(251_904));
        $this->assertSame('1.2 MB', P::fileSize(1_258_291));
    }

    public function test_the_security_events_are_the_ones_worth_scanning_for(): void
    {
        $this->assertTrue(P::isSecurityEvent('password_changed'));
        $this->assertTrue(P::isSecurityEvent('email_changed'));
        $this->assertTrue(P::isSecurityEvent('sign_in'));
        $this->assertFalse(P::isSecurityEvent('preferences_updated'));
    }

    public function test_no_activity_kind_claims_a_location(): void
    {
        // The handover's log said "New login from Kolkata, IN", which means IP
        // geolocation that does not exist. An entry that invents a fact is worse
        // than no entry on the page somebody checks when they are worried.
        foreach (P::activityKinds() as $kind) {
            $label = P::activity($kind)['label'];

            $this->assertStringNotContainsStringIgnoringCase('from', $label);
            $this->assertStringNotContainsStringIgnoringCase('location', $label);
        }
    }

    public function test_an_unknown_activity_kind_still_renders(): void
    {
        $this->assertSame('Something new', P::activity('something_new')['label']);
    }

    public function test_the_four_tabs_all_resolve_to_real_routes(): void
    {
        foreach (P::tabs() as $tab) {
            $this->assertTrue(app('router')->has($tab['route']), "{$tab['route']} does not exist");
            $this->assertNotSame('', $tab['label']);
        }
    }
}
