<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Support\Demo\DemoClients;
use Illuminate\Database\Seeder;

/**
 * The demo clients, as real rows. Local + debug only.
 *
 * Runs BEFORE AccountSeeder, because the client accounts it creates point at
 * these rows through `client_ref` — an account whose client does not exist is
 * a portal session scoped to nothing.
 *
 * Nothing here runs in production: real clients are added through the Clients
 * module by somebody who has actually signed one.
 */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        foreach (DemoClients::all() as $index => $row) {
            Client::updateOrCreate(
                // Keyed on the name, not the reference: re-running the seeder
                // must update the client that is already there rather than
                // creating a second one under a new number.
                ['name' => $row['name']],
                [
                    'reference' => 'CLT'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'industry' => $row['industry'],
                    'status' => $row['status'],
                    'contact_email' => str($row['name'])->slug()->value().'@example.com',
                ],
            );
        }
    }
}
