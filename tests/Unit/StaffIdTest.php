<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Support\Realm;
use App\Support\StaffId;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The identifier people quote at each other.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `ZEPH` + YY + D + NNN, DECIDED IN THE REVIEW ROUND (2026-09-11)
 *
 * YY is the year the record is created — not the joining year, which can be
 * backdated. D is what kind of engagement this is: 1 full-time, 2 intern,
 * 3 freelance. NNN restarts at 001 each year, within each type.
 *
 * Mentors and clients hold no employment and no year: `ZEPH4NNN` and
 * `ZEPH5NNN`, one running series each.
 *
 * The number comes from the HIGHEST existing one in its own series, never from
 * a count. A count reissues an identifier the moment a row is removed, and an
 * identifier that has belonged to two people makes every audit entry naming it
 * ambiguous.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class StaffIdTest extends TestCase
{
    /* ─────────────────────────  the shape  ───────────────────────── */

    public function test_a_full_time_employee_is_type_one(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->assertSame('ZEPH261001', StaffId::forEmployee(Employee::FULL_TIME));
    }

    public function test_an_intern_is_type_two_and_a_freelancer_type_three(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->assertSame('ZEPH262001', StaffId::forEmployee(Employee::INTERN));
        $this->assertSame('ZEPH263001', StaffId::forEmployee(Employee::FREELANCE));
    }

    public function test_the_year_is_when_the_record_is_created(): void
    {
        // Not the joining date: a record entered in 2027 for somebody who
        // started in 2024 is a 2027 record, and backdating the identifier would
        // collide with the numbers that year already issued.
        Carbon::setTestNow('2027-01-02');

        $this->assertSame('ZEPH271001', StaffId::forEmployee(Employee::FULL_TIME));
    }

    /* ─────────────────────────  the counter  ───────────────────────── */

    public function test_the_number_follows_the_highest_in_the_same_series(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->staffAccount('ZEPH261001');
        $this->staffAccount('ZEPH261002');

        $this->assertSame('ZEPH261003', StaffId::forEmployee(Employee::FULL_TIME));
    }

    public function test_each_type_counts_separately(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->staffAccount('ZEPH261001');
        $this->staffAccount('ZEPH261002');

        // Interns have issued nothing this year, so they start at 001 even
        // though full-time is already at 002.
        $this->assertSame('ZEPH262001', StaffId::forEmployee(Employee::INTERN));
    }

    public function test_each_year_starts_again_at_one(): void
    {
        $this->staffAccount('ZEPH261001');
        $this->staffAccount('ZEPH261002');

        Carbon::setTestNow('2027-01-01');

        $this->assertSame('ZEPH271001', StaffId::forEmployee(Employee::FULL_TIME));
    }

    public function test_an_identifier_is_never_reissued(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->staffAccount('ZEPH261001');
        $this->staffAccount('ZEPH261002');
        $this->staffAccount('ZEPH261003');

        // Somebody's account is removed — which the application never does, but
        // a count-based generator would hand the next person 003 all the same.
        User::where('user_id', 'ZEPH261002')->delete();

        $this->assertSame('ZEPH261004', StaffId::forEmployee(Employee::FULL_TIME));
    }

    public function test_the_old_EMP_numbering_does_not_feed_the_new_series(): void
    {
        // The fixtures and every record created before the scheme changed are
        // `EMP007`. They are a different series and must not be read as one.
        Carbon::setTestNow('2026-04-01');

        $this->staffAccount('EMP007');

        $this->assertSame('ZEPH261001', StaffId::forEmployee(Employee::FULL_TIME));
    }

    /* ─────────────────────────  the other two realms  ───────────────────────── */

    public function test_a_mentor_carries_no_year_and_no_employment_type(): void
    {
        // §2.1: a Mentor is staff with no Employee base at all, so neither half
        // of the employee scheme means anything for them.
        Carbon::setTestNow('2026-04-01');

        $this->assertSame('ZEPH4001', StaffId::forMentor());

        $this->staffAccount('ZEPH4001', 'mentor');

        $this->assertSame('ZEPH4002', StaffId::forMentor());
    }

    public function test_a_client_is_the_fifth_series(): void
    {
        $this->assertSame('ZEPH5001', StaffId::forClient());

        User::create([
            'user_id' => 'ZEPH5001',
            'name' => 'A client',
            'email' => 'client@example.test',
            'password' => 'irrelevant-to-this-test',
            'account_type' => Realm::CLIENT,
            'staff_kind' => null,
            'status' => 'active',
        ]);

        $this->assertSame('ZEPH5002', StaffId::forClient());
    }

    public function test_the_three_series_do_not_read_each_other(): void
    {
        Carbon::setTestNow('2026-04-01');

        $this->staffAccount('ZEPH4009', 'mentor');

        // A mentor at 009 must not push the next employee or client along: the
        // digit is a series, not a sequence they share.
        $this->assertSame('ZEPH261001', StaffId::forEmployee(Employee::FULL_TIME));
        $this->assertSame('ZEPH5001', StaffId::forClient());
    }

    /* ─────────────────────────  refusals  ───────────────────────── */

    public function test_an_unknown_employment_type_is_refused(): void
    {
        // Rather than defaulting to full-time and quietly issuing somebody the
        // wrong kind of identifier, which nothing downstream would notice.
        $this->expectException(\InvalidArgumentException::class);

        StaffId::forEmployee('contractor');
    }

    protected function staffAccount(string $staffId, string $kind = 'employee'): User
    {
        return User::create([
            'user_id' => $staffId,
            'name' => 'Somebody '.$staffId,
            'email' => strtolower($staffId).'@example.test',
            'password' => 'irrelevant-to-this-test',
            'account_type' => Realm::STAFF,
            'staff_kind' => $kind,
            'status' => 'active',
        ]);
    }
}
