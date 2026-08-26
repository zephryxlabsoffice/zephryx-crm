<?php

namespace Tests\Unit;

use App\Support\Shell;
use PHPUnit\Framework\TestCase;

class ShellInitialsTest extends TestCase
{
    public function test_it_takes_the_first_and_last_word(): void
    {
        $this->assertSame('SD', Shell::initials('Santanu Dev'));
        $this->assertSame('SG', Shell::initials('Santanu Kumar Ganguly'));
    }

    public function test_a_single_name_gives_one_letter(): void
    {
        $this->assertSame('S', Shell::initials('Santanu'));
    }

    public function test_it_tolerates_untidy_input(): void
    {
        $this->assertSame('SD', Shell::initials('  santanu   dev  '));
        $this->assertSame('—', Shell::initials(''));
        $this->assertSame('—', Shell::initials('   '));
    }
}
