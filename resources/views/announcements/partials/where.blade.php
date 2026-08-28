{{--
    The distinction the whole module turns on, said once where somebody might
    wonder about it: why their task assignment is not on this board.

    Replaces the handover's "Quick Actions" tile, which offered a single
    "Announcement Report" that reported nothing.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Where things appear</strong>
    </div>

    <ul class="an-facts">
        <li class="an-fact">
            <span class="an-fact-ic tone-accent" aria-hidden="true">
                @include('partials.nav-icon', ['icon' => 'announcements'])
            </span>
            <span class="an-fact-body">
                <strong>This board</strong>
                <span>Things everyone should read — policy, holidays, events, and birthdays and work anniversaries.</span>
            </span>
        </li>

        <li class="an-fact">
            <span class="an-fact-ic tone-warn" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </span>
            <span class="an-fact-body">
                <strong>Your bell</strong>
                {{-- One expression: a Blade newline before the full stop
                     renders as "See yours ." --}}
                <span>
                    Things addressed to you — a task assigned, a ticket escalated, leave decided.
                    {!! '<a class="card-link" href="'.e(route('notifications.index')).'">See yours</a>.' !!}
                </span>
            </span>
        </li>

        <li class="an-fact">
            <span class="an-fact-ic tone-soft" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </span>
            <span class="an-fact-body">
                <strong>Milestones post themselves</strong>
                <span>Birthdays and anniversaries go up on the day. You can keep your own off the board — ask HR to switch it off.</span>
            </span>
        </li>
    </ul>
</section>
