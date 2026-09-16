{{--
    What somebody is paid, for whoever holds `salary.view`.

    That is the key and not `employees.identifiers`: the identifiers permission
    is about documents, and this is about money. HR and the CEO hold both and
    nobody else holds either, so today they select the same people — but they
    answer different questions, and the day somebody is trusted with one and not
    the other, the gate is already in the right place.

    `$salary` is null when the viewer may not see this, and the controller never
    loaded the row in that case.

    THERE IS NO TOTAL ON THIS CARD. A gross computed here would be this
    application's arithmetic standing next to a payslip produced by somebody
    else's, and the two differ in any month with a deduction, an arrear or a day
    of loss of pay in it. See App\Support\SalaryStructure::card().
--}}
@php
    $card = $salary['card'];
@endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v10M9.5 9.5h4a1.8 1.8 0 0 1 0 3.6h-3a1.8 1.8 0 0 0 0 3.6h4"/>
        </svg>
        What they are paid
    </div>

    @if ($card === null)
        {{-- An absence, stated. Somebody with no agreement on file is somebody
             whose payslip has nothing to be prepared from, and that has to be
             visible rather than being a card that simply does not appear. --}}
        <div class="prose">
            <p>Nothing on file yet — no agreement has been recorded, so there are no figures to prepare a payslip from.</p>
        </div>
    @else
        <dl class="field-grid att-record-grid">
            @foreach ($card['earnings'] as $row)
                <div class="lv-field">
                    <dt class="lv-field-lbl">{{ $row['label'] }}</dt>
                    <dd>
                        {{ $row['amount'] }}
                        @if ($card['basis'] && $row['label'] === 'Rate')
                            <span class="an-optional">{{ mb_strtolower($card['basis']) }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach

            @foreach ($card['deductions'] as $row)
                <div class="lv-field">
                    {{-- Named as a deduction on the label rather than by a minus
                         sign on the figure: the stored number is what is
                         withheld, and printing it negative would invite somebody
                         to add the column up. --}}
                    <dt class="lv-field-lbl">{{ $row['label'] }} <span class="an-optional">(deducted)</span></dt>
                    <dd>{{ $row['amount'] }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="prose prose-quiet">
            <p>
                The agreement, not a payslip. Payslips are uploaded by HR and are the record
                of what was actually paid.
            </p>
        </div>
    @endif
</div>
