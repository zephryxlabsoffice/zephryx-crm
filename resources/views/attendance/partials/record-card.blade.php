@php
    use App\Support\AttendancePolicy;
    use App\Support\AttendancePresenter as P;
    $meta = P::state($record['state']);
@endphp

{{--
    One day's record, and the arithmetic that produced its status.

    Everything on this card is either one of the two recorded times or something
    derived from them and the policy. Nothing is stored, and nothing is a
    judgement — which is what lets the card show its own working. Somebody who
    disagrees with "Late" can read the check-in, the start time and the grace
    period on the same card and check it themselves.
--}}
<div class="card">
    <div class="att-record-hd">
        <div>
            <span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
            <span class="att-record-meaning">{{ $meta['meaning'] }}</span>
        </div>
        <span class="att-record-id">{{ $record['id'] }}</span>
    </div>

    <dl class="field-grid att-record-grid">
        <div class="lv-field">
            <dt class="lv-field-lbl">Checked in</dt>
            <dd>{{ P::time($record['date'], $record['check_in']) }}</dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Checked out</dt>
            <dd>
                @if ($record['check_out'] !== null)
                    {{ P::time($record['date'], $record['check_out']) }}
                @elseif ($record['auto_rejected'])
                    <span class="is-warn">Never recorded</span>
                @else
                    Still checked in
                @endif
            </dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Hours</dt>
            <dd>{{ P::hours($record['worked_minutes']) }}</dd>
        </div>

        {{-- The rule is in the label, not a tooltip. A status about somebody's
             day should never appear without the figure it was measured
             against — this is the whole judgement, and it is one comparison. --}}
        <div class="lv-field">
            <dt class="lv-field-lbl">Half day under</dt>
            <dd>{{ rtrim(rtrim(number_format($policy['halfDay'], 1), '0'), '.') }} hours</dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Day type</dt>
            <dd>
                @if ($record['holiday'] !== null)
                    {{ $record['holiday'] }}
                @elseif (! $record['working_day'])
                    Weekly off — worked anyway
                @else
                    Working day
                @endif
            </dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Counts towards the month</dt>
            <dd>
                {{-- Said in words, because this is the only question anybody
                     has about a rejected day and it should not require reading
                     a policy page to answer. --}}
                {{-- A person's rejection is named first, for the same reason as
                     in show.blade.php: when a record is both, the human reason
                     is the one that explains anything. --}}
                @if ($record['rejected_at'] !== null)
                    No — rejected
                @elseif ($record['auto_rejected'])
                    No — never checked out
                @else
                    Yes
                @endif
            </dd>
        </div>
    </dl>

    @if (! $record['working_day'] && $record['holiday'] === null)
        <p class="att-record-note">
            This was a weekly off. The record is kept and shown because the work
            happened — it is simply not counted against a day nobody was
            expected in.
        </p>
    @endif
</div>
