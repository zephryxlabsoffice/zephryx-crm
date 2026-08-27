<?php

namespace Tests\Unit;

use App\Support\SupportContact;
use Tests\TestCase;

class SupportContactTest extends TestCase
{
    public function test_it_reads_the_address_from_configuration(): void
    {
        config(['zephryx.support.email' => 'admin@example.test']);

        $this->assertSame('admin@example.test', SupportContact::address());
        $this->assertStringStartsWith('mailto:admin@example.test?subject=', SupportContact::mailto());
    }

    public function test_the_subject_is_encoded(): void
    {
        // Subjects carry an em dash and spaces; unencoded they break the link
        // in some mail clients.
        $link = SupportContact::mailto('Access — request');

        $this->assertStringContainsString(rawurlencode('Access — request'), $link);
        $this->assertStringNotContainsString(' ', $link);
    }

    public function test_it_falls_back_to_a_sensible_subject(): void
    {
        $this->assertStringContainsString(
            rawurlencode(config('zephryx.brand.name')),
            SupportContact::mailto()
        );
    }

    public function test_the_default_address_matches_the_configured_domain(): void
    {
        // Guards against the placeholder creeping back: the fallback in
        // config/zephryx.php should be the real mailbox, not an example one.
        $this->assertSame('admin@zephryxlabs.in', config('zephryx.support.email'));
    }
}
