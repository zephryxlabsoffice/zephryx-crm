<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The sidebar "Support" link (plan doc §Support).
 *
 * One page, two buttons: raise a ticket, or email us. Nothing here reads or
 * writes anything — Tickets already owns the raise-a-ticket write, and
 * App\Support\SupportContact already owns the mailto address every other
 * surface (the landing page, the login footer, the error pages) uses. This
 * controller exists only to put both in front of a signed-in staff account
 * from the sidebar, which nothing did before.
 */
class SupportController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('support.show', [
            'activeNav' => 'support',
        ]);
    }
}
