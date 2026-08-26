<?php

namespace Tests\Unit;

use App\Rules\NotACommonPassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The common-password blocklist — foundation spec §4.7.
 *
 * The point of the rule is that a short list catches a long tail of variants,
 * so most of these cases are about normalisation rather than the list itself.
 */
class NotACommonPasswordTest extends TestCase
{
    protected function fails(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [new NotACommonPassword()]]
        )->fails();
    }

    public function test_it_rejects_a_listed_password(): void
    {
        $this->assertTrue($this->fails('password'));
        $this->assertTrue($this->fails('letmein'));
        $this->assertTrue($this->fails('qwerty'));
    }

    public function test_it_rejects_case_variants(): void
    {
        $this->assertTrue($this->fails('PASSWORD'));
        $this->assertTrue($this->fails('PassWord'));
    }

    public function test_it_rejects_trailing_digits_and_punctuation(): void
    {
        // The single most common way people meet a "complexity" requirement.
        $this->assertTrue($this->fails('password123'));
        $this->assertTrue($this->fails('Password2024'));
        $this->assertTrue($this->fails('welcome!!!'));
    }

    public function test_it_rejects_character_substitutions(): void
    {
        $this->assertTrue($this->fails('p@ssw0rd'));
        $this->assertTrue($this->fails('P@$$w0rd!'));
        $this->assertTrue($this->fails('l3tm31n'));
    }

    public function test_it_rejects_company_specific_guesses(): void
    {
        $this->assertTrue($this->fails('zephryxlabs'));
        $this->assertTrue($this->fails('ZephryxLabs2026'));
    }

    public function test_it_accepts_a_genuine_passphrase(): void
    {
        $this->assertFalse($this->fails('velvet harbour ninety'));
        $this->assertFalse($this->fails('correct-horse-battery-staple'));
        $this->assertFalse($this->fails('rgLm2vQ8xTpW'));
    }

    public function test_it_does_not_reject_a_password_that_merely_contains_a_listed_word(): void
    {
        // Substring matching would reject far too much; the rule compares the
        // normalised whole, not fragments.
        $this->assertFalse($this->fails('my passport is teal'));
        $this->assertFalse($this->fails('admiralty arch sunrise'));
    }

    public function test_an_empty_value_is_left_to_the_required_rule(): void
    {
        $this->assertFalse($this->fails(''));
    }
}
