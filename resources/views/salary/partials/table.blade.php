@php
    use App\Support\Avatar;
    use App\Support\SalaryPresenter as P;
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

            {{-- The month is a real filter that lives in the URL, so a given
                 month's payroll can be linked to. The handover had a button
                 labelled "May 2024" that did nothing. --}}
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

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Employee</th>
                    <th role="columnheader" scope="col">Department</th>
                    {{-- "Salary" alone does not say gross or net. On a payroll
                         screen that is not a detail — it is the number somebody
                         reconciles against a bank statement. --}}
                    <th role="columnheader" scope="col" class="col-money">Gross</th>
                    <th role="columnheader" scope="col" class="col-money">Net pay</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Paid on</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($runs as $run)
                    @php
                        $pill = P::status($run['status']);
                        $gross = P::totalEarnings($run);
                        $net = P::net($run);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Employee">
                            <span class="name-cell">
                                <span class="avatar {{ Avatar::tint($run['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($run['employee_record']['name']) }}</span>
                                <span class="name-cell-text">
                                    <strong>{{ $run['employee_record']['name'] }}</strong>
                                    <span>{{ $run['employee'] }} · {{ $run['employee_record']['designation'] }}</span>
                                </span>
                            </span>
                        </td>

                        <td role="cell" data-label="Department">{{ $run['employee_record']['department'] }}</td>

                        <td role="cell" class="cell-money" data-label="Gross">
                            <span class="money money-quiet">{{ $gross->format() }}</span>
                        </td>

                        <td role="cell" class="cell-money" data-label="Net pay">
                            <span class="money">{{ $net->format() }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Paid on">
                            {{-- "Not paid yet" rather than the handover's "--",
                                 which reads as missing data instead of as a
                                 thing that has not happened. --}}
                            <span class="{{ $run['status'] === P::PAID ? '' : 'sl-unpaid' }}">{{ P::paidOn($run) }}</span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('salary.show', ['employee' => $run['employee'], 'period' => $run['period']]) }}">
                                <span class="sr-only">Open {{ $run['employee_record']['name'] }}’s {{ P::periodShort($run['period']) }} run</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>Nobody matches that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('salary.index', ['period' => $period]) }}">clear the filters</a>.
                                @else
                                    <strong>Payroll has not been generated for {{ P::period($period) }}.</strong>
                                    Generating creates an unpaid run for each employee with a salary structure, which somebody then releases.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($runs->total() > 0)
        @include('partials.pagination', ['paginator' => $runs, 'unit' => 'runs'])
    @endif
</div>

@include('salary.partials.generate')
