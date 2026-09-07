<?php

namespace Tests\Feature;

use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavigationGate;
use App\Support\Navigation\RbacGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

/**
 * Navigation filtering — foundation spec §5.
 */
class NavigationTest extends TestCase
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
    public function test_it_drops_entries_the_viewer_may_not_see(): void
    {
        $this->app->bind(NavigationGate::class, fn () => new class implements NavigationGate
        {
            public function allows(?Authenticatable $user, string $permission): bool
            {
                return $permission === 'employees.view';
            }
        });

        $items = $this->app->make(Navigation::class)->for(null);

        $this->assertSame(['employees'], array_column($items, 'key'));
    }

    public function test_a_viewer_with_no_permissions_gets_an_empty_navigation(): void
    {
        $this->app->bind(NavigationGate::class, fn () => new class implements NavigationGate
        {
            public function allows(?Authenticatable $user, string $permission): bool
            {
                return false;
            }
        });

        $this->assertSame([], $this->app->make(Navigation::class)->for(null));
    }

    public function test_the_active_key_marks_exactly_one_entry(): void
    {
        // A gate that allows everything, so this test is about the active flag
        // and not about who holds `projects.view`.
        $this->app->bind(NavigationGate::class, fn () => new class implements NavigationGate
        {
            public function allows(?Authenticatable $user, string $permission): bool
            {
                return true;
            }
        });

        $items = $this->app->make(Navigation::class)->for(null, 'projects');

        $active = array_values(array_filter($items, fn (array $item) => $item['active']));

        $this->assertCount(1, $active);
        $this->assertSame('projects', $active[0]['key']);
    }

    public function test_the_navigation_gate_is_the_rbac_engine(): void
    {
        /*
         * This replaced a test asserting that PermissiveGate threw in
         * production (2026-09-07). That test existed to make forgetting the
         * swap loud; the swap has happened, PermissiveGate is deleted, and what
         * is worth guarding now is the opposite — that nothing quietly rebinds
         * this contract back to something permissive.
         */
        $this->assertInstanceOf(RbacGate::class, $this->app->make(NavigationGate::class));
    }

    public function test_nobody_is_not_somebody(): void
    {
        // An unauthenticated viewer holds nothing at all, so the shell renders
        // an empty sidebar rather than the whole application.
        $this->assertSame([], $this->app->make(Navigation::class)->for(null));
    }

    public function test_every_navigation_entry_declares_a_permission_key(): void
    {
        foreach (config('navigation') as $item) {
            $this->assertArrayHasKey('permission', $item, "[{$item['key']}] has no permission key");
            $this->assertNotEmpty($item['permission']);
        }
    }
}
