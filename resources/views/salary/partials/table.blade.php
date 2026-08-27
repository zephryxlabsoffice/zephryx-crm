@php
    use App\Support\Avatar;
    use App\Support\SalaryPresenter as P;
    $payable = $records->filter(fn (array $r) => P::isPayable($r));
@endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">Payroll · {{ P::period($period) }}</span>

        <form class="table-tools" method="GET" action="{{ route('salary.index') }}">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="salary-search">Search employees</label>
                <input id="salary-search" type="search" name="q" value="{{ $search }}" placeholder="Search name or staff ID…">
            </div>

            {{-- The month is a real filter living in the URL, so a given month's
                 payroll can be linked to. The handover had a button labelled
                 "May 2024" that did nothing. --}}
            <label class="sr-only" for="salary-period">Month</label>
            <select class="chip-btn" id="salary-period" name="period" data-auto-submit>
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="salary-department">Department</label>
            <select class="chip-btn" id="salary-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="salary-status">Status</label>
            <select class="chip-btn" id="salary-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::statusOptions() as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('salary.index', ['period' => $period]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    {{--
        The bulk flow.

        Ticking rows and pressing the button does NOT mark anybody paid — it
        posts to a confirmation page that names who is about to be paid and asks.
        Marking twelve people paid by mis-click is hard to notice and awkward to
        undo, so the destructive step is always the second one.

        The whole table lives inside this form, so a row's checkbox and the
        submit button are the same submission. No JavaScript is required for it
        to work; the select-all box is a progressive enhancement.
    --}}
    <form method="POST" action="{{ route('salary.pay.confirm') }}">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">

        <div class="card-body-table">
            <table class="data-table data-table-stack sl-table" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col" class="col-check">
                            @if ($payable->isNotEmpty())
                                <label class="sr-only" for="select-all">Select every payable row</label>
                                <input id="select-all" type="checkbox" data-select-all="pay-row">
                            @else
                                <span class="sr-only">Select</span>
                            @endif
                        </th>
                        <th role="columnheader" scope="col">Employee</th>
                        <th role="columnheader" scope="col">Department</th>
                        <th role="columnheader" scope="col">Payslip</th>
                        <th role="columnheader" scope="col" class="col-money">Net pay</th>
                        <th role="columnheader" scope="col">Status</th>
                        <th role="columnheader" scope="col">Paid on</th>
                        <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($records as $record)
                        @php
                            $pill = P::status(P::statusOf($record));
                            $recordUrl = route('salary.show', ['employee' => $record['employee'], 'period' => $record['period']]);
                        @endphp
                        <tr role="row">
                            <td role="cell" class="col-check" data-label="Select">
                                @if (P::isPayable($record))
                                    <label class="sr-only" for="pay-{{ $record['employee'] }}">Mark {{ $record['employee_record']['name'] }} paid</label>
                                    <input id="pay-{{ $record['employee'] }}" type="checkbox" name="employees[]" value="{{ $record['employee'] }}" data-select-row="pay-row">
                                @else
                                    {{-- No checkbox rather than a disabled one:
                                         a row with no payslip cannot be paid,
                                         and a greyed box invites the click
                                         anyway. --}}
                                    <span class="sr-only">Not payable</span>
                                @endif
                            </td>

                            <td role="cell" class="cell-lead" data-label="Employee">
                                <a class="row-link" href="{{ $recordUrl }}">
                                    <span class="name-cell">
                                        <span class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($record['employee_record']['name']) }}</span>
                                        <span class="name-cell-text">
                                            <strong>{{ $record['employee_record']['name'] }}</strong>
                                            <span>{{ $record['employee'] }} · {{ $record['employee_record']['designation'] }}</span>
                                        </span>
                                    </span>
                                </a>
                            </td>

                            <td role="cell" data-label="Department">{{ $record['employee_record']['department'] }}</td>

                            <td role="cell" data-label="Payslip">
                                @if ($record['payslip'])
                                    <span class="sl-slip-on">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                        </svg>
                                        Added {{ P::date($record['payslip']['added_on']) }}
                                    </span>
                                @else
                                    <span class="sl-slip-off">None yet</span>
                                @endif
                            </td>

                            <td role="cell" class="cell-money" data-label="Net pay">
                                <span class="money {{ $record['net'] === null ? 'money-quiet' : '' }}">{{ P::net($record) }}</span>
                            </td>

                            <td role="cell" class="cell-tight" data-label="Status">
                                <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                            </td>

                            <td role="cell" class="cell-tight" data-label="Paid on">
                                {{-- "Not paid yet" rather than the handover's
                                     "--", which reads as missing data instead
                                     of as something that has not happened. --}}
                                <span class="{{ $record['paid_on'] ? '' : 'sl-unpaid' }}">{{ P::paidOn($record) }}</span>
                            </td>

                            <td role="cell" class="cell-actions cell-actions-wide" data-label="Action">
                                <a class="btn btn-outline btn-sm" href="{{ $recordUrl }}">
                                    {{ $record['payslip'] ? 'Open' : 'Add payslip' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="8">
                                <div class="table-empty">
                                    @if ($filtered)
                                        <strong>Nobody matches that search.</strong>
                                        Try a different term, or <a class="card-link" href="{{ route('salary.index', ['period' => $period]) }}">clear the filters</a>.
                                    @else
                                        <strong>No salary records for {{ P::period($period) }}.</strong>
                                        Records appear for every active employee once the month begins.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payable->isNotEmpty())
            <div class="sl-bulk">
                <p class="sl-bulk-note">
                    {{ $payable->count() }} {{ \Illuminate\Support\Str::plural('person', $payable->count()) }}
                    {{ $payable->count() === 1 ? 'has' : 'have' }} a payslip on file and
                    {{ $payable->count() === 1 ? 'has' : 'have' }} not been paid.
                    Tick them and you will be asked to confirm before anything is marked.
                </p>
                <button class="btn btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Mark payment done
                </button>
            </div>
        @endif
    </form>

    @if ($records->total() > 0)
        @include('partials.pagination', ['paginator' => $records, 'unit' => 'records'])
    @endif
</div>
