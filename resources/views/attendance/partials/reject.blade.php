{{--
    Rejecting a record — the only thing HR can do to somebody's attendance, and
    the only thing they need to be able to do.

    ─────────────────────────────────────────────────────────────────────────────
    WHAT THIS IS NOT

    It is not an approval, and there is no approve button anywhere in this module
    to pair it with. A check-in is a fact; it counts the moment it is made. This
    is a correction applied to a record that turned out to be wrong — a duplicate
    from a shared login, a door reader that double-fired — and it is expected to
    be used a handful of times a month, not on every day of every person.

    It is also not an edit. The times stay exactly as they were recorded, on
    screen, next to the reason they are not being counted. Letting somebody type
    a more plausible check-out time in would turn an attendance record into a
    record of the last person to touch it.

    And it is not the ten-hour rule. A day left open past the window is already
    rejected, by the clock rather than by anybody, so this card does not appear
    on one — there is nothing left to reject, and nothing to restore either.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<div class="card att-reject">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
            <path d="M12 9v4M12 17h.01"/>
        </svg>
        {{ $canRestore ? 'Undo this rejection' : 'Reject this record' }}
    </div>

    @if ($canRestore)
        <div class="prose">
            <p>
                Restoring puts the day back into {{ $record['employee_record']['name'] }}’s
                counts exactly as it was recorded. The times were never changed,
                so there is nothing to put back — only the rejection to lift.
                {{-- Both acts are audited, and saying so is part of what stops
                     either being used casually (§6). --}}
                Both the rejection and this reversal stay on the record.
            </p>
        </div>

        <form method="POST" action="{{ route('attendance.restore', ['record' => $record['id']]) }}">
            @csrf
            {{-- Behind `attendance.reject` (§2.6), audited (§6), and refused on
                 a record the actor owns. --}}
            <button class="btn btn-outline" type="submit">
                Restore the record
            </button>
        </form>
    @else
        <div class="prose">
            <p>
                Rejecting removes this day from {{ $record['employee_record']['name'] }}’s
                attendance counts. The recorded times stay on the record and stay
                visible — this marks the day as not counted and says why, it does
                not change or delete anything.
            </p>
        </div>

        <form class="att-reject-form" method="POST" action="{{ route('attendance.reject', ['record' => $record['id']]) }}">
            @csrf

            <div class="form-field">
                <label class="form-field-lbl" for="reject-reason">Why is this record wrong?</label>
                <textarea id="reject-reason" name="reason" rows="3" required minlength="10" maxlength="1000"
                          placeholder="e.g. Duplicate — the same day was recorded from the reception tablet under a shared login.">{{ old('reason') }}</textarea>
                @error('reason')
                    <span class="field-error">{{ $message }}</span>
                @enderror
                {{-- Required, not optional. This is somebody's attendance
                     record, and "rejected" with no explanation is the version
                     they have to come and ask about in person. --}}
                <span class="prose-quiet">
                    Required, and shown to {{ $record['employee_record']['name'] }} on this page.
                </span>
            </div>

            <button class="btn btn-outline btn-danger" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Reject this record
            </button>
        </form>
    @endif
</div>
