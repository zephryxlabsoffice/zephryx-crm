{{--
    Four tiles, every figure from `$stats`. The handover hardcoded 7 / 12 / 18 / 6.

    "Requested" leads because it is the only one that means somebody has to do
    something: a client has asked for a meeting and nobody has created it yet.
--}}
<section class="kpi-row" aria-label="Meeting summary">

    <div class="kpi">
        <div class="kpi-ic {{ $stats['requested'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Requested</div>
            <div class="kpi-val">{{ number_format($stats['requested']) }}</div>
            <span class="kpi-sub">Asked for, not yet created</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Scheduled</div>
            <div class="kpi-val">{{ number_format($stats['scheduled']) }}</div>
            <span class="kpi-sub">On Google, invites sent</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Ended</div>
            <div class="kpi-val">{{ number_format($stats['ended']) }}</div>
            {{-- Not "Completed". Nothing here can see whether a meeting
                 happened — Google knows a room existed, not whether anybody
                 joined it. --}}
            <span class="kpi-sub">Their time has passed</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Cancelled</div>
            <div class="kpi-val">{{ number_format($stats['cancelled']) }}</div>
            {{-- Cancelled is not "declined". Calling a meeting off and turning
                 down an invite are different acts by different people; the
                 handover's tile conflated them. --}}
            <span class="kpi-sub">Called off, invites withdrawn</span>
        </div>
    </div>

</section>
