@php
    use App\Support\Avatar;
    use App\Support\LeavePresenter as P;
    $pill = P::status($request['status']);
@endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Requested by</strong>
    </div>

    <div class="rail-list">
        <div class="rail-row">
            <span class="avatar {{ Avatar::tint($request['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($request['employee_record']['name']) }}</span>
            <div class="rail-body">
                <strong>{{ $request['employee_record']['name'] }}</strong>
                <span>{{ $request['employee_record']['designation'] }} · {{ $request['employee_record']['department'] }}</span>
            </div>
        </div>

        @if ($request['decider_record'])
            <div class="rail-row">
                <span class="avatar {{ Avatar::tint($request['decider_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($request['decider_record']['name']) }}</span>
                <div class="rail-body">
                    <strong>{{ $request['decider_record']['name'] }}</strong>
                    <span>{{ $request['status'] === P::APPROVED ? 'Approved it' : 'Decided it' }}</span>
                </div>
            </div>
        @endif
    </div>

    <div>
        <div class="stat-row">
            <span class="stat-label">Reference</span>
            <span class="stat-value stat-value-mono">{{ $request['id'] }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Staff ID</span>
            <span class="stat-value stat-value-mono">{{ $request['employee'] }}</span>
        </div>
        <div class="stat-row stat-row-block">
            <span class="stat-label">What it means</span>
            <span class="stat-value stat-value-quiet">{{ $pill['meaning'] }}</span>
        </div>
    </div>
</section>
