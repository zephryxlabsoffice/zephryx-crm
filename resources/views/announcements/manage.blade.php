@extends('layouts.app')

@php use App\Support\AnnouncementPresenter as P; @endphp

@section('title', 'Manage announcements')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('announcements.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                The board
            </a>
            <h1>Manage announcements</h1>
            <p>Everything written, including drafts and what has expired.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('announcements.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Post an announcement
            </a>
        </div>
    </div>

    @include('announcements.partials.kpis')

    <div class="card table-card">
        <nav class="tabs" aria-label="Announcement states">
            @foreach (['all' => 'All', 'active' => 'Active', 'scheduled' => 'Scheduled', 'draft' => 'Drafts', 'expired' => 'Expired'] as $key => $label)
                <a class="tab @if ($tab === $key) active @endif"
                   href="{{ route('announcements.manage', ['tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span class="tab-count">{{ $tabCounts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="card-hd">
            <span class="card-title">Written announcements</span>

            <form class="table-tools" method="GET" action="{{ route('announcements.manage') }}">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="search-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <label class="sr-only" for="an-search">Search announcements</label>
                    <input id="an-search" type="search" name="q" value="{{ $search }}" placeholder="Search title or text…">
                </div>

                <label class="sr-only" for="an-category">Category</label>
                <select class="chip-btn" id="an-category" name="category" data-auto-submit>
                    <option value="">All categories</option>
                    @foreach (P::authorableCategories() as $key => $meta)
                        <option value="{{ $key }}" @selected($filterCategory === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>

                <button class="chip-btn" type="submit">Search</button>

                @if ($filtered)
                    <a class="chip-btn chip-btn-accent" href="{{ route('announcements.manage', ['tab' => $tab]) }}">Clear filters</a>
                @endif
            </form>
        </div>

        <div class="card-body-table">
            <table class="data-table data-table-stack" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col">Announcement</th>
                        <th role="columnheader" scope="col">Category</th>
                        <th role="columnheader" scope="col">Posted by</th>
                        <th role="columnheader" scope="col">Audience</th>
                        <th role="columnheader" scope="col">Runs</th>
                        <th role="columnheader" scope="col">Status</th>
                        <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($announcements as $item)
                        @php
                            $pill = P::status($item['status']);
                            $cat = P::category($item['category']);
                            $runs = P::runsUntil($item);
                        @endphp
                        <tr role="row">
                            <td role="cell" class="cell-lead" data-label="Announcement">
                                <a class="row-link" href="{{ route('announcements.show', ['announcement' => $item['id']]) }}">
                                    <strong class="an-row-title">{{ $item['title'] }}</strong>
                                    <span class="an-row-ref">{{ $item['id'] }}</span>
                                </a>
                            </td>

                            <td role="cell" class="cell-tight" data-label="Category">
                                <span class="an-chip {{ $cat['tone'] }}">{{ $cat['label'] }}</span>
                            </td>

                            <td role="cell" data-label="Posted by">{{ $item['author_record']['name'] ?? '—' }}</td>

                            <td role="cell" data-label="Audience">{{ P::audienceLabel($item) }}</td>

                            <td role="cell" class="cell-tight" data-label="Runs">
                                <span class="an-runs">
                                    <strong>{{ P::date($item['published_at']) }}</strong>
                                    <span class="{{ $runs['tone'] }}">{{ $runs['label'] }}</span>
                                </span>
                            </td>

                            <td role="cell" class="cell-tight" data-label="Status">
                                <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                            </td>

                            <td role="cell" class="cell-actions cell-actions-wide" data-label="Open">
                                <a class="btn btn-outline btn-sm" href="{{ route('announcements.show', ['announcement' => $item['id']]) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="7">
                                <div class="table-empty">
                                    @if ($filtered)
                                        <strong>Nothing matches that search.</strong>
                                        Try a different term, or <a class="card-link" href="{{ route('announcements.manage', ['tab' => $tab]) }}">clear the filters</a>.
                                    @else
                                        <strong>Nothing here.</strong>
                                        <a class="card-link" href="{{ route('announcements.create') }}">Post an announcement</a>.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($announcements->total() > 0)
            @include('partials.pagination', ['paginator' => $announcements, 'unit' => 'announcements'])
        @endif
    </div>

    {{-- Said once, here, because this is the page where somebody might wonder
         why they cannot find yesterday's birthday post to edit. --}}
    <p class="an-manage-note">
        Birthdays and work anniversaries are not listed: they are computed from
        employee records rather than written, so there is nothing to edit,
        schedule or delete. Somebody who would rather not be announced can be
        opted out on their employee record.
    </p>
@endsection
