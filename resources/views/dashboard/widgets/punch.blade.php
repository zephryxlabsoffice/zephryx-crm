@php
    use App\Support\AttendancePresenter as AP;
    use Illuminate\Support\Carbon;

    $meta = AP::state($w['state']['state']);
@endphp

{{--
    Checking in, on the page most people open first.

    The same act as the card on /attendance/mine, and deliberately the same
    shape: one button, no fields. The person is the session and the clock is the
    server's, so there is nothing to fill in and nothing to tamper with. See the
    head of App\Http\Controllers\AttendanceController.

    This is the second place the button appears, which is one more than ideal —
    but the dashboard is where somebody lands at 09:20, and making them navigate
    to punch in is how a check-in gets forgotten until 11:00.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Today</strong>
        <span class="dash-quiet-meta">{{ Carbon::today()->format('D, d M') }}</span>
    </div>

    <div class="dash-punch-state">
        <span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Checked in</span>
        <span class="stat-value">{{ AP::time(Carbon::today()->toDateString(), $w['today']['check_in'] ?? null) }}</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Checked out</span>
        <span class="stat-value">
            @if ($w['checkedOut'])
                {{ AP::time($w['today']['date'], $w['today']['check_out']) }}
            @elseif ($w['checkedIn'])
                <span class="stat-value-quiet">Still in</span>
            @else
                <span class="stat-value-quiet">—</span>
            @endif
        </span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Hours so far</span>
        <span class="stat-value">{{ AP::hours($w['state']['worked_minutes']) }}</span>
    </div>

    <div class="dash-punch-action">
        @if (! $w['workingDay'])
            {{-- A day the office is shut is not a day somebody failed to check
                 in on, and offering the button would imply it was. --}}
            <p class="dash-note">
                {{ $w['holiday'] ? $w['holiday'].' — the office is closed.' : 'Not a working day.' }}
            </p>
        @elseif (! $w['checkedIn'])
            <form method="POST" action="{{ route('attendance.check-in') }}">
                @csrf
                {{-- TODO (backend phase): server clock, unique on (employee,
                     date) so a double submit makes one record, audited (§6). --}}
                <button class="btn btn-primary" type="submit" disabled title="Checking in is not built yet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Check in
                </button>
            </form>
        @elseif (! $w['checkedOut'])
            <form method="POST" action="{{ route('attendance.check-out') }}">
                @csrf
                <button class="btn btn-primary" type="submit" disabled title="Checking out is not built yet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Check out
                </button>
            </form>
            {{-- The consequence, said before it happens rather than discovered
                 at the end of the month. --}}
            <p class="dash-note">
                Left open more than {{ $w['window'] }} hours and the day stops counting.
            </p>
        @else
            <p class="dash-note">Both times are in for today.</p>
        @endif

        <a class="dash-link" href="{{ route('attendance.mine') }}">Your attendance</a>
    </div>
</section>
