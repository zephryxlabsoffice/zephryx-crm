<?php

namespace Tests\Unit;

use App\Support\ClientPresenter;
use PHPUnit\Framework\TestCase;

class ClientPresenterTest extends TestCase
{
    public function test_it_maps_known_statuses_to_a_pill(): void
    {
        $this->assertSame(['tone' => 'pill-green', 'label' => 'Active'], ClientPresenter::status('active'));
        $this->assertSame(['tone' => 'pill-gray', 'label' => 'Inactive'], ClientPresenter::status('inactive'));
    }

    public function test_an_unknown_status_still_renders_readably(): void
    {
        // A status added to the database before this map is updated must not
        // produce a blank cell.
        $this->assertSame(
            ['tone' => 'pill-gray', 'label' => 'Awaiting brief'],
            ClientPresenter::status('awaiting_brief')
        );
    }

    public function test_payment_states_map_to_their_own_tones(): void
    {
        $this->assertSame('pill-red', ClientPresenter::payment('due')['tone']);
        $this->assertSame('pill-green', ClientPresenter::payment('paid')['tone']);
    }

    public function test_the_identity_tint_is_stable_and_in_range(): void
    {
        // Derived from the name so the same client is always the same colour,
        // and so an eighth client does not fall through to a default — the
        // handover hand-numbered av-1..av-7 against seven sample rows.
        $first = ClientPresenter::tint('GreenLeaf Foods');

        $this->assertSame($first, ClientPresenter::tint('GreenLeaf Foods'));
        $this->assertSame($first, ClientPresenter::tint('  greenleaf foods  '));

        foreach (['A', 'Zephryx', 'Kolkata Craft Collective', 'X Y Z', '9'] as $name) {
            $this->assertMatchesRegularExpression('/^tint-[1-7]$/', ClientPresenter::tint($name));
        }
    }

    public function test_the_initial_handles_untidy_names(): void
    {
        $this->assertSame('G', ClientPresenter::initial('GreenLeaf Foods'));
        $this->assertSame('A', ClientPresenter::initial('  abc pvt ltd'));
        $this->assertSame('?', ClientPresenter::initial('   '));
    }
}
