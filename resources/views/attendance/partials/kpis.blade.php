@php use App\Support\AttendancePresenter as P; @endphp

{{--
    Five tiles, every one of them a count of the roll below.

    The handover had six, and one of them was "Work From Home" — a number
    nothing in the flow could ever produce, because checking in captures a time
    and nothing else. A tile that can only ever read zero, or worse be filled in
    by hand, is a tile that teaches people the strip is decorative. It is gone
    until there is something that records where somebody worked.

    Its "Late" tile is gone too (2026-09-03), along with the concept. Three
    statuses — present, half day, absent — plus the two reasons a day is not
    being judged: leave, and rejection.
--}}
<section class="kpi-row" aria-label="Attendance summary for the selected day">

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 14 11 16 15 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Present</div>
            <div class="kpi-val">{{ $stats[P::PRESENT] }}</div>
            <span class="kpi-sub">Of {{ $stats['headcount'] }} on the roll</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $stats[P::HALF_DAY] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 0 0 20z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Half day</div>
            <div class="kpi-val">{{ $stats[P::HALF_DAY] }}</div>
            <span class="kpi-sub">
                {{-- The threshold is named, because "half day" without it is a
                     judgement nobody can check. --}}
                Under {{ rtrim(rtrim(number_format(\App\Support\AttendancePolicy::halfDayHours(), 1), '0'), '.') }} hours
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $stats[P::ABSENT] > 0 ? 'tone-danger' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <line x1="18" y1="8" x2="22" y2="12"/><line x1="22" y1="8" x2="18" y2="12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Absent</div>
            <div class="kpi-val">{{ $stats[P::ABSENT] }}</div>
            <span class="kpi-sub">
                {{-- Nobody is absent at half past nine. While the day is still
                     running the honest word is "not in yet". --}}
                @if ($isToday && $stats[P::NOT_MARKED] > 0)
                    {{ $stats[P::NOT_MARKED] }} not in yet
                @else
                    No record on a working day
                @endif
            </span>
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
            <div class="kpi-lbl">On leave</div>
            <div class="kpi-val">{{ $stats[P::LEAVE] }}</div>
            {{-- Read from the Leave module, never stored here. A day the
                 company granted must not turn up as an absence. --}}
            <span class="kpi-sub">Approved leave, not absence</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic {{ $stats[P::REJECTED] > 0 ? 'tone-danger' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Rejected</div>
            <div class="kpi-val">{{ $stats[P::REJECTED] }}</div>
            {{-- Two ways in: HR corrected a wrong record, or the day was left
                 open past the window. If this number is ever large the problem
                 is the second one, and it is a habit rather than a fault. --}}
            <span class="kpi-sub">Corrected, or never closed</span>
        </div>
    </div>

</section>
