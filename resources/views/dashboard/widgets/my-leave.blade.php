@php use App\Support\LeavePresenter as LP; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Your leave</strong>
        <a class="dash-link" href="{{ route('leave.mine') }}">All</a>
    </div>

    <div class="stat-row">
        <span class="stat-label">Days remaining</span>
        {{-- Out of the entitlement, stated. "14" on its own is a number
             somebody has to go and look up the meaning of. --}}
        <span class="stat-value">{{ $w['balance']['remaining'] }} <span class="stat-value-quiet">of {{ $w['balance']['entitlement'] }}</span></span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Taken this year</span>
        <span class="stat-value">{{ $w['balance']['taken'] }}</span>
    </div>

    @if ($w['pending'] > 0)
        <div class="stat-row">
            <span class="stat-label">Awaiting a decision</span>
            <span class="stat-value"><span class="is-soon">{{ $w['pending'] }}</span></span>
        </div>
    @endif

    @if ($w['next'])
        <div class="dash-next">
            <span class="dash-quiet-meta">Next time off</span>
            <strong>{{ LP::range($w['next']) }}</strong>
            <span class="dash-quiet-meta">{{ LP::duration($w['next']) }} · {{ \App\Support\LeavePolicy::label($w['next']['type']) }}</span>
        </div>
    @endif

    <div class="dash-punch-action">
        <a class="btn btn-outline" href="{{ route('leave.create') }}">Request leave</a>
    </div>
</section>
