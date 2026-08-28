{{--
    Three tiles, all from `$balance`, which is entitlement minus approved days.

    The handover had five and they contradicted each other: its policy card
    granted 34 days a year, its "Taken" tile said 12, and every balance figure
    said 18 rather than the 22 those two imply. It also showed an "Expired
    Leaves" tile reading zero for a rule that does not exist, and gave unpaid
    leave a two-day balance on the same screen where the policy said unpaid has
    no allowance.
--}}
<section class="kpi-row" aria-label="Your leave summary">

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 14 11 16 15 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Left this year</div>
            <div class="kpi-val">{{ $balance['remaining'] }}</div>
            <span class="kpi-sub">Of {{ $balance['entitlement'] }} days granted</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Taken</div>
            <div class="kpi-val">{{ $balance['taken'] }}</div>
            <span class="kpi-sub">
                {{ $balance['unpaid'] > 0 ? 'Plus '.$balance['unpaid'].' unpaid' : 'Approved days so far' }}
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $balance['pending'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Awaiting a decision</div>
            <div class="kpi-val">{{ $balance['pending'] }}</div>
            {{-- Pending days are not deducted from the balance above, and this
                 says so — otherwise somebody plans around days they may not
                 get. --}}
            <span class="kpi-sub">Not deducted until approved</span>
        </div>
    </div>

</section>
