@php
    use App\Support\Avatar;
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
@endphp

<div class="card table-card">
    {{-- Tabs are links with their own URL, so a queue is bookmarkable and the
         back button works. The handover used buttons wired up by an inline
         <script>, which our CSP blocks — they would not have switched at all.

         "Pending" is the default rather than "All": the page exists to clear a
         queue. --}}
    <nav class="tabs" aria-label="Leave queues">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Withdrawn', 'all' => 'All'] as $key => $label)
            <a class="tab @if ($tab === $key) active @endif"
               href="{{ route('leave.index', ['tab' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}
                <span class="tab-count">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="card-hd">
        <span class="card-title">Requests</span>

        <form class="table-tools" method="GET" action="{{ route('leave.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="leave-search">Search requests</label>
                <input id="leave-search" type="search" name="q" value="{{ $search }}" placeholder="Search name, staff ID or reference…">
            </div>

            <label class="sr-only" for="leave-type">Leave type</label>
            <select class="chip-btn" id="leave-type" name="type" data-auto-submit>
                <option value="">All leave types</option>
                @foreach (LeavePolicy::types() as $key => $meta)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>

            {{-- No "All Locations" filter. The company has one office; a filter
                 whose dropdown has a single entry teaches people the controls
                 are decorative. --}}
            <label class="sr-only" for="leave-department">Department</label>
            <select class="chip-btn" id="leave-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('leave.index', ['tab' => $tab]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Employee</th>
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
                        $timing = P::timing($item);
                        $url = route('leave.show', ['leaveRequest' => $item['id']]);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Employee">
                            <a class="row-link" href="{{ $url }}">
                                <span class="name-cell">
                                    <span class="avatar {{ Avatar::tint($item['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($item['employee_record']['name']) }}</span>
                                    <span class="name-cell-text">
                                        <strong>{{ $item['employee_record']['name'] }}</strong>
                                        <span>{{ $item['employee'] }} · {{ $item['employee_record']['department'] }}</span>
                                    </span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Type">
                            <span class="lv-type {{ $leaveType['tone'] }}">{{ $leaveType['label'] }}</span>
                        </td>

                        <td role="cell" data-label="Dates">
                            <span class="lv-dates">
                                <strong>{{ P::range($item) }}</strong>
                                {{-- How soon it starts, because that is what
                                     makes one decision more urgent than
                                     another. --}}
                                @if ($item['status'] === P::PENDING)
                                    <span class="{{ $timing['tone'] }}">{{ $timing['label'] }}</span>
                                @endif
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Days">{{ P::duration($item) }}</td>

                        {{-- The reason is deliberately NOT a column. "Fever,
                             seeing a doctor tomorrow" is health information, and
                             a list of twelve people is not where it belongs. It
                             is on the request, for the person deciding it. --}}

                        <td role="cell" class="cell-tight" data-label="Applied">{{ P::date($item['applied_at']) }}</td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions cell-actions-wide" data-label="Open">
                            {{--
                                One link to the request, not a tick and a cross
                                in the row.

                                The handover put two unlabelled icon buttons
                                thirty pixels apart, one of which rejects
                                somebody's holiday. Deciding happens on the
                                request, where the dates, the reason, the
                                person's balance and who else is already off are
                                all on screen — which is what the decision
                                actually turns on.
                            --}}
                            <a class="btn btn-outline btn-sm" href="{{ $url }}">
                                {{ P::isDecidable($item) ? 'Review' : 'Open' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>Nothing matches that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('leave.index', ['tab' => $tab]) }}">clear the filters</a>.
                                @elseif ($tab === P::PENDING)
                                    <strong>Nothing waiting on you.</strong>
                                    Every request has been decided.
                                @else
                                    <strong>No requests here.</strong>
                                    Leave requests appear in this list as people submit them.
                                @endif
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
