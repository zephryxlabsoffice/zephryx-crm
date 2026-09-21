@extends('layouts.app')

@php use Illuminate\Support\Carbon; @endphp

@section('title', 'Comp-off requests')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('attendance.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Attendance
            </a>
            <h1>Comp-off requests</h1>
            <p>Only the requests inside your own team, unless you hold wider authority over Teams.</p>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-hd">
            <span class="card-title">Taking a comp-off</span>
        </div>

        @if ($requests->isEmpty())
            <div class="card-body">
                <p class="rail-empty">Nothing waiting on a decision.</p>
            </div>
        @else
            <div class="card-body-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Person</th>
                            <th scope="col">Earned</th>
                            <th scope="col">Wants to take</th>
                            <th scope="col">Decide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['employee_record']['name'] ?? '—' }}</strong>
                                    <span class="dash-sub">{{ $row['employee_record']['department'] ?? '' }}</span>
                                </td>
                                <td class="cell-tight">{{ Carbon::parse($row['earned_on'])->format('d M Y') }}</td>
                                <td class="cell-tight">{{ $row['take_date'] ? Carbon::parse($row['take_date'])->format('d M Y') : '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('compoffs.approve', ['compOff' => $row['id']]) }}">
                                        @csrf
                                        <button class="btn btn-outline btn-sm" type="submit">Approve</button>
                                    </form>

                                    <form method="POST" action="{{ route('compoffs.reject', ['compOff' => $row['id']]) }}">
                                        @csrf
                                        <div class="form-field">
                                            <label class="sr-only" for="reject-note-{{ $row['id'] }}">Reason</label>
                                            <textarea id="reject-note-{{ $row['id'] }}" name="note" rows="2"
                                                      required minlength="5" maxlength="1000"
                                                      placeholder="Why this cannot be taken then"></textarea>
                                        </div>
                                        <button class="btn btn-outline btn-danger btn-sm" type="submit">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card table-card">
        <div class="card-hd">
            <span class="card-title">Working a Sunday against leave already taken</span>
        </div>

        @if ($sundayAgainstLeave->isEmpty())
            <div class="card-body">
                <p class="rail-empty">Nothing waiting on a decision.</p>
            </div>
        @else
            <div class="card-body-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Person</th>
                            <th scope="col">Leave request</th>
                            <th scope="col">Sunday</th>
                            <th scope="col">Decide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sundayAgainstLeave as $entry)
                            <tr>
                                <td>{{ $entry->employee?->user?->name }}</td>
                                <td class="cell-tight">
                                    <a class="row-link" href="{{ route('leave.show', ['leaveRequest' => $entry->leaveRequest->reference]) }}">
                                        {{ $entry->leaveRequest->reference }}
                                    </a>
                                </td>
                                <td class="cell-tight">{{ $entry->date->format('D, d M Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('sundayAgainstLeave.approve', ['sundayRequest' => $entry->id]) }}">
                                        @csrf
                                        <button class="btn btn-outline btn-sm" type="submit">Approve</button>
                                    </form>

                                    <form method="POST" action="{{ route('sundayAgainstLeave.reject', ['sundayRequest' => $entry->id]) }}">
                                        @csrf
                                        <div class="form-field">
                                            <label class="sr-only" for="sal-note-{{ $entry->id }}">Reason</label>
                                            <textarea id="sal-note-{{ $entry->id }}" name="note" rows="2"
                                                      required minlength="5" maxlength="1000"
                                                      placeholder="Why this cannot be worked instead"></textarea>
                                        </div>
                                        <button class="btn btn-outline btn-danger btn-sm" type="submit">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
