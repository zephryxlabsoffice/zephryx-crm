@php
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
    $pill = P::status($request['status']);
    $leaveType = LeavePolicy::type($request['type']);
    $timing = P::timing($request);
@endphp

<div class="card">
    <div class="lv-req-hd">
        <span class="lv-type {{ $leaveType['tone'] }}">{{ $leaveType['label'] }}</span>
        <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
        @if ($request['status'] === P::PENDING && $timing['tone'])
            <span class="lv-timing {{ $timing['tone'] }}">{{ $timing['label'] }}</span>
        @endif
    </div>

    <dl class="field-grid lv-req-grid">
        <div class="lv-field">
            <dt>Dates</dt>
            <dd>{{ P::range($request) }}</dd>
        </div>
        <div class="lv-field">
            <dt>Days</dt>
            {{-- Stated by the requester, agreed by the approver. Nothing here
                 works it out — see App\Support\LeavePolicy for why this module
                 counts rather than decides. --}}
            <dd>{{ P::duration($request) }}</dd>
        </div>
        <div class="lv-field">
            <dt>Applied</dt>
            <dd>{{ P::dateTime($request['applied_at']) }}</dd>
        </div>
        <div class="lv-field">
            <dt>Decided by</dt>
            <dd>{{ $request['decider_record']['name'] ?? 'Nobody yet' }}</dd>
        </div>
    </dl>

    <div class="lv-reason">
        <span class="lv-field-lbl">Reason given</span>
        <p>{{ $request['reason'] }}</p>
    </div>

    @if ($request['contact'])
        <div class="lv-reason">
            <span class="lv-field-lbl">Contact while away</span>
            {{-- A phone number is personal data (§6). It appears on the request
                 for the person deciding it and on no list — which is why there
                 is no contact column in the queue. --}}
            <p class="lv-contact">{{ $request['contact'] }}</p>
        </div>
    @endif
</div>
