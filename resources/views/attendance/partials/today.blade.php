@php
    use App\Support\AttendancePolicy;
    use App\Support\AttendancePresenter as P;

    $meta = P::state($todayState['state']);
    $checkedIn = $today !== null && $today['check_in'] !== null;
    $checkedOut = $today !== null && $today['check_out'] !== null;
@endphp

{{--
    Checking in and out.

    ─────────────────────────────────────────────────────────────────────────────
    ONE BUTTON, AND IT ONLY EVER DOES ONE THING

    Not a form. There is nothing to fill in: no date, no time, no reason, no
    "mark attendance for" dropdown. The person is the session and the clock is
    the server's, so the entire act is a POST with a CSRF token and nothing else
    — which is also why there is no field for anybody to tamper with.

    Which button shows is decided by the record, not by the time of day: check in
    if there is no record, check out if there is an open one, and neither once
    the day is closed. A disabled-looking button that silently does nothing is
    worse than no button.

    The handover's equivalent was a "Mark Attendance" page that submitted a
    request into an approval queue. There is no queue: see the head of
    App\Http\Controllers\AttendanceController.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<section class="rail-card att-today">
    <div class="rail-hd">
        <strong>Today</strong>
        <span class="att-today-date">{{ \Illuminate\Support\Carbon::today()->format('D, d M') }}</span>
    </div>

    <div class="att-today-state">
        <span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
        <span class="att-today-meaning">{{ $meta['meaning'] }}</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Checked in</span>
        <span class="stat-value">{{ P::time(\Illuminate\Support\Carbon::today()->toDateString(), $today['check_in'] ?? null) }}</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Checked out</span>
        <span class="stat-value">
            @if ($checkedOut)
                {{ P::time($today['date'], $today['check_out']) }}
            @elseif ($checkedIn)
                <span class="stat-value-quiet">Still in</span>
            @else
                <span class="stat-value-quiet">—</span>
            @endif
        </span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Hours so far</span>
        <span class="stat-value">{{ P::hours($todayState['worked_minutes']) }}</span>
    </div>

    @if ($todayState['auto_rejected'])
        {{-- The window has already closed on today. Said here rather than
             discovered at the end of the month. --}}
        <div class="stat-row">
            <span class="stat-label">Left open</span>
            <span class="stat-value"><span class="is-soon">Over {{ (int) AttendancePolicy::autoRejectAfterHours() }} hours</span></span>
        </div>
    @endif

    <div class="att-today-action">
        @if (! $checkedIn)
            <form method="POST" action="{{ route('attendance.check-in') }}">
                @csrf
                {{-- The server's clock, never a posted time; unique on
                     (employee, date) so a double submit makes one record;
                     audited (§6). --}}
                <button class="btn btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Check in
                </button>
            </form>
            <p class="att-today-note">Recorded at the server's clock, the moment you press it.</p>
        @elseif (! $checkedOut)
            <form method="POST" action="{{ route('attendance.check-out') }}">
                @csrf
                <button class="btn btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Check out
                </button>
            </form>
            <p class="att-today-note">
                {{-- The consequence, stated before it happens rather than
                     discovered at the end of the month. It is the whole reason
                     the ten-hour rule is safe to have: nobody can say they
                     were not told. --}}
                @if ($todayState['auto_rejected'])
                    This day has been open more than {{ (int) AttendancePolicy::autoRejectAfterHours() }} hours,
                    so it no longer counts. Checking out now will not bring it back.
                @else
                    Left open more than {{ (int) AttendancePolicy::autoRejectAfterHours() }} hours, the day
                    stops counting — there is no way to fill a check-out in later.
                @endif
            </p>
        @else
            <p class="att-today-note">
                Both times are in for today. Neither can be changed; if the day
                is wrong, HR rejects the record with a reason.
            </p>
        @endif
    </div>
</section>
