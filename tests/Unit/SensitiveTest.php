<?php

namespace Tests\Unit;

use App\Support\Sensitive;
use Tests\TestCase;

class SensitiveTest extends TestCase
{
    /* ─────────────────────────  Aadhaar  ───────────────────────── */

    public function test_aadhaar_shows_only_the_last_four_digits(): void
    {
        // UIDAI's own rule for a masked Aadhaar.
        $this->assertSame('XXXX XXXX 1234', Sensitive::aadhaar('123456781234'));
        $this->assertSame('XXXX XXXX 1234', Sensitive::aadhaar('1234 5678 1234'));
        $this->assertSame('XXXX XXXX 1234', Sensitive::aadhaar('1234-5678-1234'));
    }

    public function test_aadhaar_never_leaks_the_leading_digits(): void
    {
        $masked = Sensitive::aadhaar('987654321098');

        $this->assertStringNotContainsString('9876', $masked);
        $this->assertStringNotContainsString('5432', $masked);
        $this->assertStringEndsWith('1098', $masked);
    }

    /* ─────────────────────────  PAN  ───────────────────────── */

    public function test_pan_shows_only_the_last_four_characters(): void
    {
        $this->assertSame('XXXXXX234F', Sensitive::pan('ABCDE1234F'));
        $this->assertStringNotContainsString('ABCDE', Sensitive::pan('ABCDE1234F'));
    }

    public function test_pan_is_normalised_before_masking(): void
    {
        $this->assertSame('XXXXXX234F', Sensitive::pan('  abcde1234f  '));
    }

    /* ─────────────────────────  bank account  ───────────────────────── */

    public function test_an_account_number_shows_only_the_last_four(): void
    {
        $this->assertSame('•••• •••• 7890', Sensitive::accountNumber('50100234567890'));
        $this->assertStringNotContainsString('5010', Sensitive::accountNumber('50100234567890'));
    }

    public function test_the_mask_does_not_reveal_the_length(): void
    {
        // Indian account numbers run 9–18 digits. Rendering the true length
        // narrows a guess and helps the reader not at all.
        $short = Sensitive::accountNumber('123456789');
        $long = Sensitive::accountNumber('123456789012345678');

        $this->assertSame(strlen($short), strlen($long));
    }

    /* ─────────────────────────  IFSC  ───────────────────────── */

    public function test_ifsc_is_not_masked(): void
    {
        // It identifies a branch, not a person, is published by the RBI and is
        // printed on every cheque. Masking it would imply the fields beside it
        // are protected by obscurity too.
        $this->assertSame('HDFC0001234', Sensitive::ifsc('hdfc0001234'));
    }

    /* ─────────────────────────  absence  ───────────────────────── */

    public function test_nothing_recorded_reads_as_nothing_recorded(): void
    {
        foreach ([null, '', '  ', '12'] as $empty) {
            $this->assertSame(Sensitive::ABSENT, Sensitive::aadhaar($empty));
            $this->assertSame(Sensitive::ABSENT, Sensitive::accountNumber($empty));
        }

        $this->assertSame(Sensitive::ABSENT, Sensitive::pan(null));
        $this->assertSame(Sensitive::ABSENT, Sensitive::ifsc(null));
    }

    public function test_a_mask_never_returns_its_input_unchanged(): void
    {
        // The failure that matters: a masking function that quietly passes the
        // value through for some input shape.
        foreach (['123456781234', '1234 5678 9012', '999999999999'] as $aadhaar) {
            $this->assertNotSame($aadhaar, Sensitive::aadhaar($aadhaar));
        }

        foreach (['ABCDE1234F', 'ZZZZZ9999Z'] as $pan) {
            $this->assertNotSame($pan, Sensitive::pan($pan));
        }

        foreach (['50100234567890', '004501556789'] as $account) {
            $this->assertNotSame($account, Sensitive::accountNumber($account));
        }
    }

    /* ─────────────────────────  who may see  ───────────────────────── */

    public function test_only_a_person_themselves_may_see_their_identifiers(): void
    {
        $this->assertTrue(Sensitive::viewerMaySee('EMP002', 'EMP002'));
        $this->assertFalse(Sensitive::viewerMaySee('EMP002', 'EMP004'));
    }

    public function test_an_unknown_viewer_may_see_nothing(): void
    {
        // Fails towards withholding. Until the RBAC engine exists, a viewer we
        // cannot identify gets nothing rather than everything.
        $this->assertFalse(Sensitive::viewerMaySee(null, 'EMP002'));
    }
}
