@php
    use App\Support\AttendancePolicy;
    use App\Support\Holidays;
    use Illuminate\Support\Carbon;

    $offDays = collect(AttendancePolicy::weekOff())
        ->map(fn (int $day) => Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($day)->format('l'))
        ->join(', ');

    $hours = fn (float $value) => rtrim(rtrim(number_format($value, 1), '0'), '.');

    $nextHoliday = Holidays::next();
@endphp

{{--
    The attendance policy, read from wherever it is configured.

    Nothing here is written into the module. This card renders whatever the
    configuration says; today that is `config/attendance.php`, which exists only
    until the Admin Panel ships (§12).

    It is on every page in this module on purpose. "Half day" is a word about
    somebody's pay, and it should never appear on a screen that does not also
    say what it was measured against.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Attendance policy</strong>
    </div>

    <div class="stat-row">
        <span class="stat-label">
            <span class="stat-ic tone-accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            Working day
        </span>
        <span class="stat-value">{{ AttendancePolicy::workStart() }} – {{ AttendancePolicy::workEnd() }}</span>
    </div>

    {{-- The one threshold. There is no grace period and no full-day figure:
         a grace period only exists to define a "late" status this module does
         not have, and a second hours figure would create a fourth status
         nobody asked for. --}}
    <div class="stat-row">
        <span class="stat-label">
            <span class="stat-ic tone-accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 0 0 20z"/>
                </svg>
            </span>
            Counts as half a day under
        </span>
        <span class="stat-value">{{ $hours(AttendancePolicy::halfDayHours()) }} hours</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">
            <span class="stat-ic tone-danger" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                    <path d="M12 9v4M12 17h.01"/>
                </svg>
            </span>
            No check-out within
        </span>
        <span class="stat-value">{{ $hours(AttendancePolicy::autoRejectAfterHours()) }} hours</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">
            <span class="stat-ic tone-accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </span>
            Weekly off
        </span>
        <span class="stat-value">{{ $offDays ?: 'None' }}</span>
    </div>

    {{--
        The next holiday, from the announcement that declared it.

        Not from a list in this module's configuration. HR posts "office closed
        on the 14th" once, and that notice is both what everybody reads and the
        day nobody is marked absent for — see App\Support\Holidays. The link
        goes to what HR actually wrote rather than restating it here.
    --}}
    <div class="stat-row">
        <span class="stat-label">
            <span class="stat-ic tone-alt" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v18M5 8h14M7 21h10"/>
                </svg>
            </span>
            Next holiday
        </span>
        <span class="stat-value">
            @if ($nextHoliday === null)
                <span class="stat-value-quiet">None announced</span>
            @elseif ($nextHoliday['announcement'] !== null)
                <a class="card-link" href="{{ route('announcements.show', ['announcement' => $nextHoliday['announcement']]) }}">
                    {{ \Illuminate\Support\Carbon::parse($nextHoliday['date'])->format('d M') }}
                </a>
            @else
                {{ \Illuminate\Support\Carbon::parse($nextHoliday['date'])->format('d M') }}
            @endif
        </span>
    </div>

    <p class="att-rail-note">
        @if ($nextHoliday !== null)
            <strong>{{ $nextHoliday['name'] }}</strong> —
        @endif
        holidays come from the holiday announcements on the board, so there is
        no second list to fall out of step with what people were told. Hours and
        weekly offs are set in the Admin Panel.
    </p>
</section>
