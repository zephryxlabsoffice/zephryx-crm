@extends('layouts.app')

@php
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
@endphp

@section('title', 'My Leave')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            {{-- Reached by a button on the approval queue, so it needs a way
                 back — but only for the people who could have come from there.
                 TODO (backend phase): gate on `leave.approve`. --}}
            @if ($canApprove)
                <a class="back-link" href="{{ route('leave.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Leave requests
                </a>
            @endif
            <h1>My Leave</h1>
            <p>What you have left, and everything you have asked for.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('leave.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Request leave
            </a>
        </div>
    </div>

    @include('leave.partials.mine-kpis')

    <section class="lv-grid">
        <div class="card table-card">
            <nav class="tabs" aria-label="Your leave">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Withdrawn'] as $key => $label)
                    <a class="tab @if ($tab === $key) active @endif"
                       href="{{ route('leave.mine', ['tab' => $key]) }}"
                       @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                        <span class="tab-count">{{ $tabCounts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="card-hd">
                <span class="card-title">My requests</span>
            </div>

            <div class="card-body-table">
                <table class="data-table data-table-stack" role="table">
                    <thead>
                        <tr role="row">
                            <th role="columnheader" scope="col">Type</th>
                            <th role="columnheader" scope="col">Dates</th>
                            <th role="columnheader" scope="col">Days</th>
                            <th role="columnheader" scope="col">Applied</th>
                            <th role="columnheader" scope="col">Status</th>
                            <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $item)
                            @php
                                $pill = P::status($item['status']);
                                $leaveType = LeavePolicy::type($item['type']);
                            @endphp
                            <tr role="row">
                                <td role="cell" class="cell-lead" data-label="Type">
                                    <a class="row-link" href="{{ route('leave.show', ['leaveRequest' => $item['id']]) }}">
                                        <span class="lv-type {{ $leaveType['tone'] }}">{{ $leaveType['label'] }}</span>
                                    </a>
                                </td>
                                <td role="cell" data-label="Dates">{{ P::range($item) }}</td>
                                <td role="cell" class="cell-tight" data-label="Days">{{ P::duration($item) }}</td>
                                <td role="cell" class="cell-tight" data-label="Applied">{{ P::date($item['applied_at']) }}</td>
                                <td role="cell" class="cell-tight" data-label="Status">
                                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                                </td>
                                <td role="cell" class="cell-actions cell-actions-wide" data-label="Open">
                                    <a class="btn btn-outline btn-sm" href="{{ route('leave.show', ['leaveRequest' => $item['id']]) }}">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr role="row">
                                <td role="cell" colspan="6">
                                    <div class="table-empty">
                                        <strong>Nothing here.</strong>
                                        Requests you make appear in this list.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->total() > 0)
                @include('partials.pagination', ['paginator' => $requests, 'unit' => 'requests'])
            @endif
        </div>

        <aside class="rail">
            @include('leave.partials.balance', ['heading' => 'Your balance'])
            @include('leave.partials.policy')
        </aside>
    </section>
@endsection
