@php use App\Support\SalaryPresenter as P; @endphp

{{--
    Earnings less deductions equals net.

    This is the arithmetic claim the whole module makes, so it is shown as
    arithmetic: the lines, their totals, and the subtraction between them. Net
    is computed here on every render, never read from a stored column — the
    person most likely to be looking at this is the one being paid, and a total
    that disagrees with its own lines in front of that audience is the worst
    version of this bug.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
            <path d="M9 13h6"/><path d="M9 17h4"/>
        </svg>
        {{ $heading ?? 'Breakdown' }}
        <span class="tab-count">{{ P::period($run['period']) }}</span>
    </div>

    <div class="sl-breakdown">
        <div class="sl-column">
            <h4 class="sl-column-hd">Earnings</h4>
            <dl class="sl-lines">
                @foreach ($run['earnings'] as $line)
                    <div class="sl-line">
                        <dt>
                            {{ $line['label'] }}
                            <span class="sl-line-note">{{ $line['note'] }}</span>
                        </dt>
                        <dd class="money">{{ $line['amount']->format() }}</dd>
                    </div>
                @endforeach
                <div class="sl-line sl-line-total">
                    <dt>Gross</dt>
                    <dd class="money">{{ P::totalEarnings($run)->format() }}</dd>
                </div>
            </dl>
        </div>

        <div class="sl-column">
            <h4 class="sl-column-hd">Deductions</h4>

            @if ($run['deductions'] === [])
                {{--
                    Stated, not omitted.

                    ZephryxLabs withholds nothing today (decided 2026-08-27):
                    EPF is not mandatory below twenty employees, ESI below ten,
                    and no professional tax or TDS is being deducted. An
                    employee should be able to SEE that nothing was taken —
                    which is different from not being told.
                --}}
                <p class="sl-none">
                    <strong>Nothing deducted.</strong>
                    No provident fund, professional tax or income tax is
                    withheld, so your gross is your net.
                </p>
            @else
                <dl class="sl-lines">
                    @foreach ($run['deductions'] as $line)
                        <div class="sl-line">
                            <dt>
                                {{ $line['label'] }}
                                <span class="sl-line-note">{{ $line['note'] }}</span>
                            </dt>
                            <dd class="money">− {{ $line['amount']->format() }}</dd>
                        </div>
                    @endforeach
                    <div class="sl-line sl-line-total">
                        <dt>Total deductions</dt>
                        <dd class="money">− {{ P::totalDeductions($run)->format() }}</dd>
                    </div>
                </dl>
            @endif
        </div>
    </div>

    <div class="sl-net">
        <span class="sl-net-lbl">Net pay</span>
        <strong class="sl-net-val money">{{ P::net($run)->format() }}</strong>
        <span class="sl-net-note">
            {{ P::totalEarnings($run)->format() }} earnings
            @if ($run['deductions'] !== [])
                less {{ P::totalDeductions($run)->format() }} deductions
            @else
                and nothing deducted
            @endif
        </span>
    </div>
</div>
