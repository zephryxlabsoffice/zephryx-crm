@php
    use App\Support\AttendancePresenter as P;

    /**
     * "4 more than last month", not "+12% vs last month".
     *
     * The handover printed a percentage delta under every tile — 12%, 20%, 50%,
     * 8% — all four hardcoded, and none of them meaningful anyway: a percentage
     * change on a count of two late days is a hundred per cent, which sounds
     * like a crisis and means one day. Whole days, said in words, or nothing.
     */
    $delta = function (int $now, int $before) {
        $difference = $now - $before;

        if ($difference === 0) {
            return ['label' => 'Same as last month', 'tone' => ''];
        }

        return [
            'label' => abs($difference).' '.($difference > 0 ? 'more' : 'fewer').' than last month',
            'tone' => '',
        ];
    };
@endphp

<section class="kpi-row" aria-label="Your attendance this month">

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 14 11 16 15 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Days attended</div>
            <div class="kpi-val">{{ $summary['attended'] }}</div>
            {{-- Out of working days ELAPSED, not the whole month. On the 3rd,
                 "3 of 22" reads as a disaster and "3 of 3" reads as the truth. --}}
            <span class="kpi-sub">Of {{ $summary['working_days'] }} working days so far</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $summary[P::HALF_DAY] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 0 0 20z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Half days</div>
            <div class="kpi-val">{{ $summary[P::HALF_DAY] }}</div>
            <span class="kpi-sub">{{ $delta($summary[P::HALF_DAY], $previousSummary[P::HALF_DAY])['label'] }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $summary[P::ABSENT] > 0 ? 'tone-danger' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Absent</div>
            <div class="kpi-val">{{ $summary[P::ABSENT] }}</div>
            {{-- Leave is counted separately and said so here, because the first
                 question anybody asks of an absence count is whether their
                 approved holiday is in it. --}}
            <span class="kpi-sub">
                {{ $summary[P::LEAVE] > 0 ? $summary[P::LEAVE].' leave days counted separately' : 'Approved leave is not counted here' }}
            </span>
        </div>
    </div>

    {{--
        There is no "average working day" tile (removed 2026-09-03).

        Over a handful of days an average swings wildly on one short afternoon,
        it hides the days that actually differ, and it is the number people
        start managing to — staying at a desk to protect a figure is not the
        behaviour this module should be encouraging. The calendar below shows
        every day individually, which is the same information without the
        summary that misleads.
    --}}
    <div class="kpi">
        <div class="kpi-ic {{ $summary[P::REJECTED] > 0 ? 'tone-danger' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Not counted</div>
            <div class="kpi-val">{{ $summary[P::REJECTED] }}</div>
            {{-- Almost always the same cause, so it names it rather than making
                 somebody open each one to find out. --}}
            <span class="kpi-sub">
                {{ $summary[P::REJECTED] > 0 ? 'Rejected, or never checked out' : 'Every day closed properly' }}
            </span>
        </div>
    </div>

</section>
