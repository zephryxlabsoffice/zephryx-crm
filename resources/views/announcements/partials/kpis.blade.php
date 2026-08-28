{{--
    Four tiles, every figure from `$stats`. The handover hardcoded 36 / 12 / 4 / 3.

    Drafts lead: a draft is the one state where somebody meant to say something
    and it never went out.
--}}
<section class="kpi-row" aria-label="Announcement summary">

    <div class="kpi">
        <div class="kpi-ic {{ $stats['draft'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Drafts</div>
            <div class="kpi-val">{{ number_format($stats['draft']) }}</div>
            <span class="kpi-sub">Written, never sent</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">On the board</div>
            <div class="kpi-val">{{ number_format($stats['active']) }}</div>
            <span class="kpi-sub">Live right now</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Scheduled</div>
            <div class="kpi-val">{{ number_format($stats['scheduled']) }}</div>
            <span class="kpi-sub">Go up on their date</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Expired</div>
            <div class="kpi-val">{{ number_format($stats['expired']) }}</div>
            {{-- Kept, not deleted: what the company said and when is worth
                 being able to look up. --}}
            <span class="kpi-sub">Kept, but off the board</span>
        </div>
    </div>

</section>
