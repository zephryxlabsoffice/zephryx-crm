{{--
    Marking a never-closed day present (v1.2.1).

    For somebody who worked the day and forgot to check out. The day counts as
    attended and reads "Present (warning)". It is not an edit: the check-out
    stays missing, no time is typed in, and the day carries no hours. The
    reason is required and shown to the person, like a rejection's.
--}}
<div class="card att-reject">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        Mark this day present
    </div>

    <div class="prose">
        <p>
            For a day {{ $record['employee_record']['name'] }} worked but forgot to
            check out of. It counts as attended and shows as "Present (warning)".
            The check-out stays missing, so the day has no hours.
        </p>
    </div>

    <form class="att-reject-form" method="POST" action="{{ route('attendance.mark-present', ['record' => $record['id']]) }}">
        @csrf

        <div class="form-field">
            <label class="form-field-lbl" for="present-reason">Why does this day count?</label>
            <textarea id="present-reason" name="present_reason" rows="3" required minlength="10" maxlength="1000"
                      placeholder="e.g. Was in the office all day (confirmed by the team lead); forgot to check out.">{{ old('present_reason') }}</textarea>
            @error('present_reason')
                <span class="field-error">{{ $message }}</span>
            @enderror
            <span class="prose-quiet">
                Required, and shown to {{ $record['employee_record']['name'] }} on this page.
            </span>
        </div>

        <button class="btn btn-primary" type="submit">Mark present</button>
    </form>
</div>
