@php
    use App\Support\Avatar;
    use App\Support\AttendancePresenter as P;
@endphp

{{--
    Days somebody checked into and never checked out of.

    ─────────────────────────────────────────────────────────────────────────────
    THE ONE ACTIONABLE THING ON THIS PAGE

    The handover's rail had six quick-action tiles — Bulk Mark Attendance, Import,
    Export, Holiday Calendar — none of which went anywhere. This replaces them
    with the only attendance problem that recurs and that a person can actually
    fix: a record with a check-in and no check-out.

    Each of these has already stopped counting — past the window a day is
    rejected, because there is no honest way to say how long it ran. The list is
    therefore not a queue of things to fix; those days are gone. It is here so
    the pattern is visible while it is still worth a conversation, which is the
    only thing that actually stops it happening again.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Never checked out</strong>
        @if ($openRecords->isNotEmpty())
            <span class="tab-count">{{ $openRecords->count() }}</span>
        @endif
    </div>

    @if ($openRecords->isEmpty())
        <p class="rail-empty">Nothing outstanding. Every day in the last fortnight was closed off.</p>
    @else
        <div class="rail-list">
            @foreach ($openRecords->take(6) as $record)
                <a class="rail-row" href="{{ route('attendance.show', ['record' => $record['id']]) }}">
                    <span class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($record['employee_record']['name']) }}</span>
                    <span class="rail-body">
                        <strong>{{ $record['employee_record']['name'] }}</strong>
                        <span>In at {{ P::time($record['date'], $record['check_in']) }} · no check-out</span>
                    </span>
                    <span class="rail-time">{{ \Illuminate\Support\Carbon::parse($record['date'])->format('d M') }}</span>
                </a>
            @endforeach
        </div>

        <p class="att-rail-note">
            {{-- Said explicitly, because the obvious "fix" is to type a
                 plausible check-out time in, and that is the one thing this
                 module must never let anybody do. --}}
            Open more than {{ (int) \App\Support\AttendancePolicy::autoRejectAfterHours() }} hours, so these no longer
            count. Nobody can fill them in — a recorded time is never edited, and
            a check-out that was never made cannot be invented.
        </p>
    @endif
</section>
