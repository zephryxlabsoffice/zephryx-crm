{{--
    Marking a never-closed day present (v1.2.1), or correcting it under a ticket
    (owner, 2026-11-07).

    Forgot to check out: the day counts as attended and reads "Present
    (warning)", with no hours. A technical issue, with a ticket the employee
    raised about it: HR enters the real check-out, the day counts with its hours,
    and the record and the ticket point at each other. The reason is required
    and shown to the person either way.
--}}
@php $alreadyMarked = $record['marked_present']; @endphp

<div class="card att-reject">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        {{ $alreadyMarked ? 'Correct this day under a ticket' : 'Mark this day present' }}
    </div>

    <div class="prose">
        <p>
            @if ($alreadyMarked)
                Was it a technical issue? Enter the ticket {{ $record['employee_record']['name'] }}
                raised about it and the time they actually left. The warning goes, and the day
                counts with its hours.
            @else
                Forgot to check out: leave the ticket empty. The day counts as attended and
                shows as "Present (warning)", with no hours.
                @if ($mayCorrectUnderTicket ?? false)
                    A technical issue: enter the ticket {{ $record['employee_record']['name'] }}
                    raised about it and the time they actually left. The day counts with its hours,
                    with no warning.
                @endif
            @endif
        </p>
    </div>

    <form class="att-reject-form" method="POST" action="{{ route('attendance.mark-present', ['record' => $record['id']]) }}">
        @csrf

        @if ($mayCorrectUnderTicket ?? false)
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-field-lbl" for="present-ticket">
                        Ticket @unless ($alreadyMarked)<span class="an-optional">(technical issue only)</span>@endunless
                    </label>
                    <input id="present-ticket" name="ticket_reference" type="text" maxlength="32"
                           placeholder="TKT-2026-001" value="{{ old('ticket_reference') }}" @required($alreadyMarked)>
                    @error('ticket_reference')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="present-check-out">
                        Actually left at @unless ($alreadyMarked)<span class="an-optional">(with a ticket)</span>@endunless
                    </label>
                    <input id="present-check-out" name="check_out" type="time" value="{{ old('check_out') }}" @required($alreadyMarked)>
                    @error('check_out')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        @endif

        <div class="form-field">
            <label class="form-field-lbl" for="present-reason">{{ $alreadyMarked ? 'What went wrong?' : 'Why does this day count?' }}</label>
            <textarea id="present-reason" name="present_reason" rows="3" required minlength="10" maxlength="1000"
                      placeholder="e.g. The check-out button failed (see the ticket); left at 6:30 PM, confirmed by the team lead.">{{ old('present_reason') }}</textarea>
            @error('present_reason')
                <span class="field-error">{{ $message }}</span>
            @enderror
            <span class="prose-quiet">
                Required, and shown to {{ $record['employee_record']['name'] }} on this page.
            </span>
        </div>

        <button class="btn btn-primary" type="submit">{{ $alreadyMarked ? 'Correct under ticket' : 'Mark present' }}</button>
    </form>
</div>
