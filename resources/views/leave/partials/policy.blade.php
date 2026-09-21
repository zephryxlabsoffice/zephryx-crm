@php use App\Support\LeavePolicy; @endphp

{{--
    The leave policy, read from wherever it is configured.

    Nothing here is written into the module. Leave types and their annual
    entitlements are company policy and belong to the Admin Panel (§12); this
    card renders whatever that says. Today it comes from `config/leave.php`,
    which exists only until Settings ships.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Leave policy</strong>
    </div>

    <ul class="lv-policy">
        @foreach (LeavePolicy::types() as $key => $meta)
            <li class="lv-policy-row">
                <span class="lv-dot {{ $meta['tone'] }}" aria-hidden="true"></span>
                <span class="lv-policy-body">
                    <strong>{{ $meta['label'] }}</strong>
                    <span>{{ $meta['note'] }}</span>
                </span>
                <span class="lv-policy-days">
                    {{-- Unpaid leave has no allowance, and saying "0 days" would
                         read as an allowance of nothing rather than as a type
                         that works differently. --}}
                    {{ $meta['days'] === null ? 'No allowance' : $meta['days'].' days' }}
                </span>
            </li>
        @endforeach
    </ul>

    <p class="lv-policy-note">
        Set in the Admin Panel. Your leave year runs from your own joining
        month, not a company-wide calendar year, and balances shown across
        this module are for your current year
        @if (LeavePolicy::carryForward() === null)
            {{-- Said plainly rather than showing an "Expired" figure whose rule
                 does not exist yet — which the handover did, reading zero. --}}
            only; whether unused days carry into next year is not settled.
        @else
            , with up to {{ LeavePolicy::carryForward() }} days carrying forward.
        @endif
    </p>

    <p class="lv-policy-note">
        Privilege and sick leave are granted in full at the start of your
        year. Casual leave accrues a twelfth of its allowance for each whole
        month that has passed since.
    </p>
</section>
