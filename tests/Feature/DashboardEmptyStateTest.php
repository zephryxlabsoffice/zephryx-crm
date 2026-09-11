<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The dashboard of a freshly deployed install.
 *
 * Its own file because DashboardPageTest seeds the demo workforce in setUp, and
 * an empty-state assertion made after that seed asserts nothing.
 */
class DashboardEmptyStateTest extends TestCase
{
    public function test_it_renders_its_empty_states_without_demo_data(): void
    {
        // Only the production seed has run: no projects, invoices or people
        // beyond this account. The page must show that honestly rather than
        // failing on a missing key or inventing activity.
        $user = $this->signInAsStaff(['ceo']);

        Employee::create([
            'user_id' => $user->id,
            'joined_on' => Carbon::today()->subYear(),
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Amit Verma', false)
            ->assertDontSee('INV-2026-', false);
    }
}
