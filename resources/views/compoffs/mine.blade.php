@extends('layouts.app')

@php use Illuminate\Support\Carbon; @endphp

@section('title', 'My Comp-offs')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('attendance.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                My attendance
            </a>
            <h1>My Comp-offs</h1>
            <p>Earned by working a rostered Sunday or holiday in full. Each one has to be taken before the next Sunday, or it lapses.</p>
        </div>
    </div>

    <section class="kpi-row" aria-label="Comp-off summary">
        <div class="kpi">
            <div class="kpi-ic {{ $available > 0 ? 'tone-accent' : 'tone-soft' }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="kpi-body">
                <div class="kpi-lbl">Available</div>
                <div class="kpi-val">{{ $available }}</div>
                <span class="kpi-sub">{{ $available > 0 ? 'Ready to take' : 'Nothing outstanding' }}</span>
            </div>
        </div>
    </section>

    <div class="card table-card">
        <div class="card-hd">
            <span class="card-title">Ledger</span>
        </div>

        @if ($compOffs->isEmpty())
            <div class="card-body">
                <p class="rail-empty">No comp-offs earned yet.</p>
            </div>
        @else
            <div class="card-body-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Earned</th>
                            <th scope="col">Expires</th>
                            <th scope="col">Status</th>
                            <th scope="col">Take it</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($compOffs as $row)
                            <tr>
                                <td>{{ Carbon::parse($row['earned_on'])->format('d M Y') }}</td>
                                <td class="cell-tight">{{ Carbon::parse($row['expires_on'])->format('d M Y') }}</td>
                                <td class="cell-tight">
                                    @if ($row['lapsed'])
                                        <span class="pill pill-gray">Lapsed</span>
                                    @elseif ($row['status'] === 'available')
                                        <span class="pill pill-green">Available</span>
                                    @elseif ($row['status'] === 'pending')
                                        <span class="pill pill-amber">Pending — {{ $row['take_date'] ? Carbon::parse($row['take_date'])->format('d M Y') : '' }}</span>
                                    @elseif ($row['status'] === 'taken')
                                        <span class="pill pill-indigo">Taken{{ $row['take_date'] ? ' — '.Carbon::parse($row['take_date'])->format('d M Y') : '' }}</span>
                                    @else
                                        <span class="pill pill-red">Rejected</span>
                                    @endif
                                </td>
                                <td class="cell-tight">
                                    @if ($row['takeable'])
                                        <form method="POST" action="{{ route('compoffs.take', ['compOff' => $row['id']]) }}">
                                            @csrf
                                            <div class="form-field">
                                                <label class="sr-only" for="take-date-{{ $row['id'] }}">Date to take it</label>
                                                <input id="take-date-{{ $row['id'] }}" name="take_date" type="date"
                                                       min="{{ now()->toDateString() }}"
                                                       max="{{ $row['expires_on'] }}" required>
                                            </div>
                                            <button class="btn btn-outline btn-sm" type="submit">Request</button>
                                        </form>
                                    @else
                                        <span class="dash-quiet-meta">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($canDecide)
        <p class="pay-hint">
            <a class="dash-link" href="{{ route('compoffs.index') }}">See the requests waiting on a decision</a>
        </p>
    @endif
@endsection
