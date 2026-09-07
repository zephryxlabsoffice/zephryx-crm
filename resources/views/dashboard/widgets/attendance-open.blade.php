@php
    use App\Support\AttendancePolicy;
    use App\Support\AttendancePresenter as AP;
    use App\Support\Avatar;
@endphp

{{--
    Days somebody checked into and never out of.

    This is not an approval queue and there is not going to be one — a check-in
    is a fact, not a request (see the head of AttendanceController). What is
    here is the handful of days a month that are genuinely worth a human
    looking at: a day left open past the window stops counting, and the person
    it belongs to usually does not know.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Left open</span>
        <a class="card-link" href="{{ route('attendance.index') }}">Attendance</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Every day closed properly.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Who</th>
                        <th scope="col">Day</th>
                        <th scope="col">Open for</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $record)
                        <tr>
                            <td>
                                <a class="row-link person-row" href="{{ route('attendance.show', $record['id']) }}">
                                    <div class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">
                                        {{ Avatar::initials($record['employee_record']['name']) }}
                                    </div>
                                    <div class="person-body">
                                        <strong>{{ $record['employee_record']['name'] }}</strong>
                                        <span>In at {{ AP::time($record['date'], $record['check_in']) }}</span>
                                    </div>
                                </a>
                            </td>
                            <td class="cell-tight">{{ AP::date($record['date']) }}</td>
                            <td class="cell-tight">
                                {{-- Derived, never stored. The record has a
                                     check-in and no check-out; how long that
                                     has been true is the clock's answer, not a
                                     column somebody has to keep up to date. --}}
                                <span class="is-soon">
                                    {{ AP::openFor(AttendancePolicy::openMinutes($record['date'], $record['check_in'], $record['check_out'])) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body dash-more">
            <span>A day open past {{ $w['window'] }} hours stops counting.</span>
        </div>
    @endif
</section>
