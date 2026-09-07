@php
    use App\Support\AttendancePolicy;
    use App\Support\AttendancePresenter as P;
@endphp

{{--
    Every day with a record, newest first.

    Deliberately a full paginated table rather than the handover's five rows and
    a "View All" link that went nowhere. This is the list somebody opens when
    they are checking a payslip against the days they worked, and five rows is
    never the answer to that question.

    Days with no record — weekly offs, holidays, absences — are not here. They
    are on the calendar above, which is the surface that can show the absence of
    something. A table cannot: a row saying "absent" is a row somebody has to be
    written, and nothing writes it.
--}}
<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">Attendance history</span>
        <span class="count-inline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            {{ $history->total() }} <span class="unit">recorded days</span>
        </span>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Date</th>
                    <th role="columnheader" scope="col">Check-in</th>
                    <th role="columnheader" scope="col">Check-out</th>
                    <th role="columnheader" scope="col">Hours</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($history as $record)
                    @php
                        $evaluated = AttendancePolicy::evaluate($record['date'], $record);
                        $meta = P::state($evaluated['state']);
                        $url = route('attendance.show', ['record' => $record['id']]);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Date">
                            <a class="row-link" href="{{ $url }}">
                                <span class="att-date-cell">
                                    <strong>{{ P::date($record['date']) }}</strong>
                                    <span>{{ \Illuminate\Support\Carbon::parse($record['date'])->format('l') }}</span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Check-in">
                            <strong class="att-clock">{{ P::time($record['date'], $record['check_in']) }}</strong>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Check-out">
                            <span class="att-time">
                                <strong>{{ P::time($record['date'], $record['check_out']) }}</strong>
                                @if ($evaluated['auto_rejected'])
                                    <span class="is-warn">Never checked out</span>
                                @elseif ($evaluated['open'])
                                    <span>{{ P::openFor($evaluated['open_minutes']) }}</span>
                                @endif
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Hours">{{ P::hours($evaluated['worked_minutes']) }}</td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions" data-label="Open">
                            <a class="btn btn-outline btn-sm" href="{{ $url }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="6">
                            <div class="table-empty">
                                <strong>Nothing recorded yet.</strong>
                                Days appear here once you check in.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($history->total() > 0)
        @include('partials.pagination', ['paginator' => $history, 'unit' => 'days'])
    @endif
</div>
