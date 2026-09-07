<?php

namespace Tests\Unit;

use App\Support\Dashboard\DashboardComposer;
use Tests\TestCase;

/**
 * The filter the whole dashboard is built on.
 *
 * These run against a registry written here rather than the real one, because
 * the behaviour being checked is the composer's, not the configuration's — and
 * a test that broke every time a widget was added to config would get deleted.
 */
class DashboardComposerTest extends TestCase
{
    protected DashboardComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new DashboardComposer;

        config(['dashboard' => [
            'kpis' => [
                ['key' => 'b', 'permission' => 'b.view', 'icon' => 'tasks', 'weight' => 20],
                ['key' => 'a', 'permission' => 'a.view', 'icon' => 'tasks', 'weight' => 10],
            ],
            'widgets' => [
                ['key' => 'second', 'permission' => 'open', 'column' => 'main', 'weight' => 20],
                ['key' => 'first', 'permission' => 'open', 'column' => 'main', 'weight' => 10],
                ['key' => 'railed', 'permission' => 'open', 'column' => 'rail', 'weight' => 10],
                ['key' => 'shut', 'permission' => 'closed', 'column' => 'main', 'weight' => 30],
            ],
        ]]);
    }

    /**
     * @param  list<string>  $held
     * @return callable(string): bool
     */
    protected function holding(array $held): callable
    {
        return fn (string $permission) => in_array($permission, $held, true);
    }

    public function test_it_drops_what_the_viewer_does_not_hold(): void
    {
        $keys = array_column($this->composer->widgets($this->holding(['open']), 'main'), 'key');

        $this->assertSame(['first', 'second'], $keys);
    }

    public function test_it_orders_by_weight_and_not_by_the_order_written(): void
    {
        // Gaps of ten in the real registry exist so a widget can be slotted
        // between two without renumbering the file — which only works if the
        // composer sorts rather than trusting the array order.
        $keys = array_column($this->composer->widgets($this->holding(['open']), 'main'), 'key');

        $this->assertSame(['first', 'second'], $keys);
    }

    public function test_columns_are_separate(): void
    {
        $rail = array_column($this->composer->widgets($this->holding(['open']), 'rail'), 'key');

        $this->assertSame(['railed'], $rail);
    }

    public function test_an_entry_with_no_permission_key_fails_closed(): void
    {
        /*
         * The failure mode of forgetting a key has to be "the widget does not
         * appear", not "everybody sees it". A missing key is a bug either way;
         * only one of the two is discovered by somebody reading payroll.
         */
        config(['dashboard.widgets' => [
            ['key' => 'unkeyed', 'column' => 'main', 'weight' => 10],
        ]]);

        // A gate that says yes to everything, which is what development runs.
        $this->assertSame([], $this->composer->widgets(fn () => true, 'main'));
    }

    public function test_the_kpi_row_is_capped(): void
    {
        config(['dashboard.kpis' => array_map(
            fn (int $i) => ['key' => 'k'.$i, 'permission' => 'open', 'icon' => 'tasks', 'weight' => $i],
            range(1, 12),
        )]);

        $kpis = $this->composer->kpis(fn () => true);

        $this->assertCount(DashboardComposer::MAX_KPIS, $kpis);

        // The cap keeps the FIRST tiles by weight, which is how the ordering in
        // the real registry puts personal figures ahead of company-wide ones.
        $this->assertSame('k1', $kpis[0]['key']);
    }

    public function test_all_returns_both_columns(): void
    {
        $keys = array_column($this->composer->all($this->holding(['open'])), 'key');

        sort($keys);

        $this->assertSame(['first', 'railed', 'second'], $keys);
    }

    public function test_a_permission_the_viewer_lacks_is_never_asked_about_twice(): void
    {
        // The controller hydrates from all(), so anything this method drops has
        // its data left unread. Guarding the property that makes that true:
        // nothing outside the returned list survives the filter.
        $visible = $this->composer->all($this->holding(['open']));

        $this->assertNotContains('shut', array_column($visible, 'key'));
    }
}
