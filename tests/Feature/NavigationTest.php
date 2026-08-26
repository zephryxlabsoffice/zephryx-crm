<?php

namespace Tests\Feature;

use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavigationGate;
use App\Support\Navigation\PermissiveGate;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Tests\TestCase;

/**
 * Navigation filtering — foundation spec §5.
 */
class NavigationTest extends TestCase
{
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
        $items = $this->app->make(Navigation::class)->for(null, 'projects');

        $active = array_values(array_filter($items, fn (array $item) => $item['active']));

        $this->assertCount(1, $active);
        $this->assertSame('projects', $active[0]['key']);
    }

    public function test_the_placeholder_gate_refuses_to_run_in_production(): void
    {
        /*
         * The whole point of PermissiveGate. Forgetting to swap it means every
         * user sees every module — exactly the leak §5 exists to prevent — so
         * it fails loudly rather than silently allowing everything.
         */
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not run in production');

        (new PermissiveGate())->allows(null, 'employees.view');
    }

    public function test_every_navigation_entry_declares_a_permission_key(): void
    {
        foreach (config('navigation') as $item) {
            $this->assertArrayHasKey('permission', $item, "[{$item['key']}] has no permission key");
            $this->assertNotEmpty($item['permission']);
        }
    }
}
