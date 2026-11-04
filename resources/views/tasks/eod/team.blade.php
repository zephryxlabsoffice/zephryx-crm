@extends('layouts.app')

@section('title', 'Team Reports')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Team Reports</h1>
            <p>End of day reports sent to you.</p>
        </div>

        <form class="hd-actions eod-date" method="GET" action="{{ route('tasks.eod.team') }}">
            <label class="form-field-lbl" for="eod-team-date">Day</label>
            <input id="eod-team-date" type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" data-auto-submit>
            <noscript><button class="btn btn-outline btn-sm" type="submit">Open</button></noscript>
        </form>
    </div>

    @include('tasks.partials.section-tabs', ['sectionTab' => 'eod-team'])

    <div class="card table-card">
        <div class="card-body-table">
            <table class="data-table data-table-stack" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col">Employee</th>
                        <th role="columnheader" scope="col">Department</th>
                        <th role="columnheader" scope="col">Items</th>
                        <th role="columnheader" scope="col">Completed</th>
                        <th role="columnheader" scope="col">Sent</th>
                        <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr role="row">
                            <td role="cell" class="cell-lead" data-label="Employee">{{ $report->employee->user?->name ?? 'Unknown' }}</td>
                            <td role="cell" data-label="Department">{{ $report->employee->department?->name ?? '-' }}</td>
                            <td role="cell" data-label="Items">{{ $report->items->count() }}</td>
                            <td role="cell" data-label="Completed">{{ $report->items->where('status', 'completed')->count() }}</td>
                            <td role="cell" data-label="Sent">
                                {{ $report->submitted_at->format('d M, g:i A') }}
                                @if ($report->wasFiledLate()) <span class="pill pill-amber">Late</span> @endif
                            </td>
                            <td role="cell" class="cell-actions" data-label="Open">
                                <a class="btn btn-outline btn-sm" href="{{ route('tasks.eod.show', ['eod' => $report->id]) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="6">
                                <div class="table-empty"><strong>No reports for this day.</strong> Choose another day above.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
