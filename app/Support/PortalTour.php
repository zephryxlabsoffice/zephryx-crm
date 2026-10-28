<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The client portal's guided tour (owner, 2026-10-28): clients use the portal
 * now and then rather than every day, so it walks them round once, on their
 * first visit, and "Take the tour" in the help panel brings it back.
 */
class PortalTour
{
    /** Whether to show it on this page. Marks it seen the first time. */
    public static function shouldShow(?User $user, Request $request): bool
    {
        if ($user === null || $user->account_type !== Realm::CLIENT) {
            return false;
        }

        if ($request->boolean('tour')) {
            return true;
        }

        if ($user->portal_tour_seen_at !== null) {
            return false;
        }

        $user->forceFill(['portal_tour_seen_at' => now()])->save();

        return true;
    }

    /**
     * The stops, in sidebar order.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function steps(): array
    {
        return [
            ['icon' => 'dashboard', 'title' => 'Welcome to your portal',
                'text' => 'Everything between you and '.config('zephryx.brand.name').' is here. This short tour shows where things are; the menu on the left takes you to each one.'],
            ['icon' => 'projects', 'title' => 'Projects',
                'text' => 'Each project you have with us: its status, how far along it is, the deadline, and the updates we share with you.'],
            ['icon' => 'invoices', 'title' => 'Invoices',
                'text' => 'Every invoice we have sent you, whether it is paid, and the document to view or download.'],
            ['icon' => 'tickets', 'title' => 'Support Tickets',
                'text' => 'Something wrong, or a question? Raise a ticket. It reaches the team on your project, and every reply stays in one place.'],
            ['icon' => 'meetings', 'title' => 'Meetings',
                'text' => 'Ask for a meeting with a preferred date and time. Once we confirm, the Google Meet invite arrives by email and the link appears here.'],
            ['icon' => 'announcements', 'title' => 'Announcements',
                'text' => 'News we share with clients, such as holidays when our office is closed.'],
            ['icon' => 'profile', 'title' => 'My Profile',
                'text' => 'Your company details, your contact people, and your password. You can take this tour again any time from "Need a hand?".'],
        ];
    }
}
