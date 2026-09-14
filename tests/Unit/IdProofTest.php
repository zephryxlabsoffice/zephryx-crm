<?php

namespace Tests\Unit;

use App\Support\IdProof;
use App\Support\Sensitive;
use Tests\TestCase;

/**
 * The document somebody is identified by.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THIS REPLACED AN AADHAAR COLUMN (decided 2026-09-12)
 *
 * Aadhaar was held on its own, and a private employer cannot require it — the
 * Supreme Court settled that in 2018. So the record asks for ONE document that
 * carries an address, and Aadhaar is one of the four answers rather than the
 * only one.
 *
 * The number is still masked by the rule its own issuer sets: UIDAI's is that a
 * masked Aadhaar shows the last four digits and nothing else, and the same
 * shape is right for the other three without the regulation behind it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class IdProofTest extends TestCase
{
    /* ─────────────────────────  the vocabulary  ───────────────────────── */

    public function test_the_four_documents_are_the_ones_that_carry_an_address(): void
    {
        // The reason this list is these four and not a longer one: the record
        // exists to hold an identity document WITH an address on it.
        $this->assertSame(
            ['aadhaar', 'voter_id', 'passport', 'driving_licence'],
            array_keys(IdProof::TYPES),
        );
    }

    public function test_each_type_reads_as_the_document_people_call_it(): void
    {
        $this->assertSame('Aadhaar', IdProof::label('aadhaar'));
        $this->assertSame('Voter ID', IdProof::label('voter_id'));
        $this->assertSame('Passport', IdProof::label('passport'));
        $this->assertSame('Driving Licence', IdProof::label('driving_licence'));
    }

    public function test_a_record_with_no_document_says_so_rather_than_guessing(): void
    {
        // Not "Aadhaar" by default. A blank type rendered as the commonest
        // document is a label that is wrong without looking wrong.
        $this->assertSame(IdProof::UNRECORDED, IdProof::label(null));
        $this->assertSame(IdProof::UNRECORDED, IdProof::label('ration_card'));
    }

    public function test_only_the_four_are_accepted(): void
    {
        $this->assertTrue(IdProof::isType('passport'));
        $this->assertFalse(IdProof::isType('ration_card'));
        $this->assertFalse(IdProof::isType(''));
        $this->assertFalse(IdProof::isType(null));
    }

    public function test_the_dropdown_offers_every_type_and_nothing_else(): void
    {
        $this->assertSame(IdProof::TYPES, IdProof::options());
    }

    /* ─────────────────────────  masking  ───────────────────────── */

    public function test_an_aadhaar_keeps_the_grouping_its_own_regulator_requires(): void
    {
        // UIDAI's rule, unchanged by the column it now lives in.
        $this->assertSame('XXXX XXXX 1234', Sensitive::idProof('aadhaar', '1234 5678 1234'));
    }

    public function test_the_other_documents_show_their_last_four_characters(): void
    {
        // An EPIC number is ten characters: three letters, seven digits.
        $this->assertSame('XXXXXX4567', Sensitive::idProof('voter_id', 'ABC1234567'));
        $this->assertSame('XXXX5678', Sensitive::idProof('passport', 'M1235678'));
        $this->assertSame('XXXXXXXXXXX4321', Sensitive::idProof('driving_licence', 'WB1420110054321'));
    }

    public function test_a_number_is_never_returned_unchanged(): void
    {
        // The failure that matters in a masking function: some input shape it
        // quietly passes straight through.
        foreach ([
            ['aadhaar', '123456781234'],
            ['voter_id', 'ABC1234567'],
            ['passport', 'M1235678'],
            ['driving_licence', 'WB1420110054321'],
        ] as [$type, $number]) {
            $this->assertNotSame($number, Sensitive::idProof($type, $number));
        }
    }

    public function test_nothing_recorded_reads_as_nothing_recorded(): void
    {
        foreach ([null, '', '   ', '12'] as $empty) {
            $this->assertSame(Sensitive::ABSENT, Sensitive::idProof('aadhaar', $empty));
            $this->assertSame(Sensitive::ABSENT, Sensitive::idProof('passport', $empty));
        }
    }

    public function test_a_number_whose_type_is_unknown_is_withheld_entirely(): void
    {
        /*
         * Fails towards withholding. A number we cannot name the document for
         * is one we cannot state the masking rule for either — and guessing at
         * "last four" for a document that might be nine digits of something
         * else is how too much of it ends up on screen.
         */
        $this->assertSame(Sensitive::ABSENT, Sensitive::idProof(null, '123456781234'));
        $this->assertSame(Sensitive::ABSENT, Sensitive::idProof('ration_card', '123456781234'));
    }
}
