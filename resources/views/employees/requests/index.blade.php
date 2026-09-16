@extends('layouts.app')

@section('title', 'Change requests')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('employees.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Employees
            </a>
            <h1>Change requests</h1>
            <p>People asking for their own details to be corrected. Nothing has moved on any record yet.</p>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body-table">
            {{-- Roles stated explicitly: below 760px the CSS sets
                 `display: block` on these to stack them as cards, which drops a
                 table's implicit ARIA semantics. --}}
            <table class="data-table data-table-stack" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col">Who</th>
                        <th role="columnheader" scope="col">Asked for</th>
                        <th role="columnheader" scope="col">Sent</th>
                        <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rows as $row)
                        <tr role="row">
                            <td role="cell" class="cell-lead" data-label="Who">
                                <strong>{{ $row['name'] }}</strong>
                                <span class="an-optional">{{ $row['staff_id'] }}</span>
                            </td>
                            <td role="cell" data-label="Asked for">
                                {{-- Field names only. This list is read over
                                     somebody's shoulder in an open-plan office;
                                     the values are on the page you have to open
                                     deliberately. --}}
                                {{ implode(', ', $row['asks']) }}
                            </td>
                            <td role="cell" data-label="Sent">{{ $row['request']->created_at?->diffForHumans() }}</td>
                            <td role="cell">
                                @if ($row['mine'])
                                    {{-- Nobody decides their own, so there is
                                         nothing to open. Said, rather than
                                         rendered as a link that would 403. --}}
                                    <span class="an-optional">Yours — somebody else decides it</span>
                                @else
                                    <a class="btn btn-outline btn-sm"
                                       href="{{ route('employees.requests.show', ['profileRequest' => $row['request']->id]) }}">
                                        Review
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            {{-- An empty queue is the normal state and reads as
                                 one. "No results" would suggest a filter is
                                 hiding something. --}}
                            <td role="cell" colspan="4">
                                Nothing is waiting. Requests appear here when somebody asks for a
                                correction on their profile.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
