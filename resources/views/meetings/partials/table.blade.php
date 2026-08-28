@php
    use App\Support\Avatar;
    use App\Support\MeetingPresenter as P;
@endphp

<div class="card table-card">
    {{-- Tabs are links with their own URL. "Scheduled" is the default: the page
         is usually opened to find out what is coming up. --}}
    <nav class="tabs" aria-label="Meeting queues">
        @foreach (['scheduled' => 'Scheduled', 'requested' => 'Requested', 'ended' => 'Ended', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
            <a class="tab @if ($tab === $key) active @endif"
               href="{{ route('meetings.index', ['tab' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}
                <span class="tab-count">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="card-hd">
        <span class="card-title">Meetings</span>

        <form class="table-tools" method="GET" action="{{ route('meetings.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="meeting-search">Search meetings</label>
                <input id="meeting-search" type="search" name="q" value="{{ $search }}" placeholder="Search title, reference or project…">
            </div>

            <label class="sr-only" for="meeting-project">Project</label>
            <select class="chip-btn" id="meeting-project" name="project" data-auto-submit>
                <option value="">All projects</option>
                @foreach ($projects as $option)
                    <option value="{{ $option['id'] }}" @selected($project === $option['id'])>{{ $option['name'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('meetings.index', ['tab' => $tab]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Meeting</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">When</th>
                    <th role="columnheader" scope="col">Who</th>
                    {{-- One status column, not the handover's two. Its Status
                         and Action columns said the same thing in different
                         words: Upcoming/Approved, Cancelled/Declined,
                         Completed/Completed. --}}
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($meetings as $item)
                    @php
                        $pill = P::status($item['status']);
                        $timing = P::timing($item);
                        $url = route('meetings.show', ['meeting' => $item['id']]);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Meeting">
                            <a class="row-link" href="{{ $url }}">
                                <strong class="mt-title">{{ $item['title'] }}</strong>
                                <span class="mt-ref">{{ $item['id'] }}</span>
                            </a>
                        </td>

                        <td role="cell" data-label="Project">
                            {{ $item['project_record']['name'] ?? 'Internal' }}
                        </td>

                        <td role="cell" data-label="When">
                            <span class="mt-when">
                                <strong>{{ P::date($item['starts_at']) }}</strong>
                                <span>{{ P::timeRange($item) }}</span>
                                @if ($item['status'] === P::SCHEDULED && $timing['tone'])
                                    <span class="{{ $timing['tone'] }}">{{ $timing['label'] }}</span>
                                @endif
                            </span>
                        </td>

                        <td role="cell" data-label="Who">
                            @include('meetings.partials.faces', ['attendees' => $item['attendees']])
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions cell-actions-wide" data-label="Open">
                            @if (P::isJoinable($item) && $item['join_url'])
                                {{-- Only when it is nearly time, and only for
                                     somebody on the invite — the controller
                                     removed the link otherwise. --}}
                                <a class="btn btn-primary btn-sm" href="{{ $item['join_url'] }}" target="_blank" rel="noopener noreferrer">
                                    Join
                                </a>
                            @else
                                <a class="btn btn-outline btn-sm" href="{{ $url }}">Open</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="6">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>Nothing matches that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('meetings.index', ['tab' => $tab]) }}">clear the filters</a>.
                                @elseif ($tab === P::SCHEDULED)
                                    <strong>Nothing scheduled.</strong>
                                    <a class="card-link" href="{{ route('meetings.create') }}">Schedule a meeting</a> and the invite goes out from Google.
                                @else
                                    <strong>No meetings here.</strong>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meetings->total() > 0)
        @include('partials.pagination', ['paginator' => $meetings, 'unit' => 'meetings'])
    @endif
</div>
