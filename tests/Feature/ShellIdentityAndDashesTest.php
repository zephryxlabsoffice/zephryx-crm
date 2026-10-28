<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterDataItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Two owner decisions of 2026-10-28 about what every page shows.
 */
class ShellIdentityAndDashesTest extends TestCase
{
    public function test_the_corner_shows_the_position_not_the_roles(): void
    {
        // Several roles in the corner overflowed it; a position is one line.
        $user = $this->signInAsStaff(['ceo', 'hr', 'manager']);
        $designation = MasterDataItem::create(['list' => MasterDataItem::DESIGNATIONS, 'name' => 'Video Editor', 'code' => 'VE']);
        Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear(), 'designation_id' => $designation->id]);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/tb-user-info.*?Video Editor/s', $html);
        $this->assertDoesNotMatchRegularExpression('/tb-user-info.*?CEO · HR/s', $html);
    }

    public function test_no_position_shows_a_hyphen(): void
    {
        $this->signInAsStaff(['ceo']);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/tb-user-info">\s*<strong>[^<]*<\/strong>\s*<span>-<\/span>/', $html);
    }

    public function test_no_em_dash_anywhere_in_the_application(): void
    {
        // "No em dash in the website, a hyphen for every one." Checked over
        // the source so one cannot creep back in through a new page.
        $dash = "\u{2014}";
        $found = [];

        foreach (['app', 'resources', 'config', 'routes', 'database'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                if ($file->getExtension() !== 'sqlite' && str_contains($file->getContents(), $dash)) {
                    $found[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $found);
    }
}
