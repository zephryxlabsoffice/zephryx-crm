@php
    use App\Support\Avatar;
    use App\Support\LeavePresenter as LP;
@endphp

{{--
    Requests waiting on a decision.

    The viewer's own request is not in this list. Nobody decides their own
    (§2.6), owner included — filtered in DashboardData as well as refused by the
    write, because a queue that puts your own request next to an Approve button
    teaches people to find that rule out the hard way.

    There are no Approve/Reject buttons here either. Approving leave without
    seeing who else is already off is the mistake the Leave module was rebuilt
    to prevent, and that context does not fit in a dashboard card — so this is a
    queue that links, not a queue that decides.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Leave to decide</span>
        <a class="card-link" href="{{ route('leave.index') }}">View all</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Nothing waiting on you.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Who</th>
                        <th scope="col">When</th>
                        <th scope="col">Days</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $request)
                        <tr>
                            <td>
                                <a class="row-link person-row" href="{{ route('leave.show', $request['id']) }}">
                                    <div class="avatar {{ Avatar::tint($request['employee_record']['name']) }}" aria-hidden="true">
                                        {{ Avatar::initials($request['employee_record']['name']) }}
                                    </div>
                                    <div class="person-body">
                                        <strong>{{ $request['employee_record']['name'] }}</strong>
                                        <span>{{ \App\Support\LeavePolicy::label($request['type']) }}</span>
                                    </div>
                                </a>
                            </td>
                            <td class="cell-tight">{{ LP::range($request) }}</td>
                            <td class="cell-tight">{{ $request['days'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($w['total'] > $w['items']->count())
            <div class="card-body dash-more">
                <span>{{ $w['total'] - $w['items']->count() }} more waiting</span>
            </div>
        @endif
    @endif
</section>
