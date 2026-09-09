{{--
    The decision.

    Two separate forms, two labelled verbs, and rejection carries a reason.

    The handover had a tick and a cross as unlabelled icon buttons thirty pixels
    apart in a table row. Both of those are somebody's holiday. Here the two acts
    are visually and structurally distinct, they sit under the dates, the reason,
    the balance and the clash list, and the destructive one asks why — because
    "rejected" with no explanation is the version people have to chase in person.
--}}
<div class="card lv-decide">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        Your decision
    </div>

    <div class="prose">
        <p>
            {{ $request['employee_record']['name'] }} is asking for
            <strong>{{ \App\Support\LeavePresenter::duration($request) }}</strong>
            of {{ \App\Support\LeavePolicy::label($request['type']) }}.
            @if ($clashes->isNotEmpty())
                {{ $clashes->count() }} other {{ \Illuminate\Support\Str::plural('person', $clashes->count()) }}
                {{ $clashes->count() === 1 ? 'is' : 'are' }} already off across those dates — see below.
            @endif
        </p>
    </div>

    <div class="lv-decide-actions">
        <form method="POST" action="{{ route('leave.approve', ['leaveRequest' => $request['id']]) }}">
            @csrf
            {{-- Behind `leave.approve` in its own right (§2.6), audited with
                 who decided and when (§6), and with the status checked INSIDE
                 the transaction so two approvers cannot both decide the same
                 request. --}}
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Approve {{ \App\Support\LeavePresenter::duration($request) }}
            </button>
        </form>

        <form class="lv-reject" method="POST" action="{{ route('leave.reject', ['leaveRequest' => $request['id']]) }}">
            @csrf

            <div class="form-field">
                <label class="form-field-lbl" for="reject-reason">If you are rejecting, say why</label>
                <textarea id="reject-reason" name="note" rows="2" minlength="5" maxlength="1000"
                          placeholder="What would make this workable — a different week, shorter dates?">{{ old('note') }}</textarea>
                <span class="pay-hint">Sent to {{ $request['employee_record']['name'] }} with the decision.</span>
                {{-- Required on a rejection and not on an approval: "yes" needs
                     no explanation, and demanding one only produces "ok". --}}
                @error('note')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <button class="btn btn-outline btn-danger" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Reject
            </button>
        </form>
    </div>
</div>
