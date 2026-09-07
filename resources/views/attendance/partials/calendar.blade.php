@php
    use App\Support\AttendancePresenter as P;

    // Only the states that can actually appear get a legend entry, so nobody
    // hunts a calendar for a colour that is not on it.
    $shown = collect($calendar)->flatten(1)
        ->filter(fn (array $cell) => $cell['in_month'] && ! $cell['future'])
        ->pluck('state')->unique();
@endphp

{{--
    The attendance calendar.

    ─────────────────────────────────────────────────────────────────────────────
    A MONTH IS A URL

    The handover's calendar was thirty-five hand-written cells for May 2024 with
    the dots typed in, and its < > arrows were wired to an inline <script> that
    our CSP blocks — so it showed one fixed month of a past year and could not be
    moved off it.

    Here the grid is built from the records (App\Support\AttendancePresenter::
    calendar) and the arrows are ordinary links carrying `?month=YYYY-MM`. Every
    month is bookmarkable, the back button works, and it all works with
    JavaScript off.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<section class="card att-cal">
    <div class="card-hd">
        <span class="card-title">Attendance calendar</span>

        <div class="att-cal-nav">
            <a class="pg-btn" href="{{ route('attendance.mine', ['month' => $previousMonth]) }}" rel="prev">
                <span class="sr-only">Previous month</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <strong class="att-cal-month">{{ $month->format('F Y') }}</strong>

            {{-- Forward stops at the current month. There is nothing to show in
                 a month that has not happened, and an arrow that leads to an
                 empty grid is an arrow that looks broken. --}}
            <a class="pg-btn @if ($nextMonth === null) is-disabled @endif"
               href="{{ $nextMonth === null ? '#' : route('attendance.mine', ['month' => $nextMonth]) }}"
               @if ($nextMonth === null) aria-disabled="true" @endif rel="next">
                <span class="sr-only">Next month</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- A table, not a grid of divs. A calendar IS tabular data — day of the
         week across, week down — and marking it up as one is what lets a screen
         reader say "Thursday, 3" instead of reading thirty-five loose numbers. --}}
    <div class="att-cal-body">
        <table class="att-cal-grid">
            <caption class="sr-only">Your attendance for {{ $month->format('F Y') }}</caption>
            <thead>
                <tr>
                    @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
                        <th scope="col" abbr="{{ $weekday }}">{{ substr($weekday, 0, 3) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($calendar as $week)
                    <tr>
                        @foreach ($week as $cell)
                            @php $meta = P::state($cell['state']); @endphp
                            <td @class([
                                    'att-cell',
                                    'is-outside' => ! $cell['in_month'],
                                    'is-today' => $cell['today'],
                                    'is-future' => $cell['future'],
                                ])>
                                @if ($cell['record'] !== null)
                                    {{-- A day with a record opens it. The
                                         calendar is a way in, not just a
                                         picture. --}}
                                    <a class="att-cell-link" href="{{ route('attendance.show', ['record' => $cell['record']['id']]) }}">
                                        <span class="att-cell-day">{{ $cell['day'] }}</span>
                                        <span class="att-dot {{ $meta['dot'] }}" aria-hidden="true"></span>
                                        <span class="sr-only">{{ P::longDate($cell['date']) }} — {{ $meta['label'] }}</span>
                                    </a>
                                @else
                                    <span class="att-cell-day">{{ $cell['day'] }}</span>
                                    @if ($cell['in_month'] && ! $cell['future'])
                                        <span class="att-dot {{ $meta['dot'] }}" aria-hidden="true"></span>
                                        <span class="sr-only">{{ P::longDate($cell['date']) }} — {{ $meta['label'] }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <ul class="att-legend">
        @foreach ($shown as $state)
            @php $meta = P::state($state); @endphp
            <li><span class="att-dot {{ $meta['dot'] }}" aria-hidden="true"></span>{{ $meta['label'] }}</li>
        @endforeach
    </ul>
</section>
