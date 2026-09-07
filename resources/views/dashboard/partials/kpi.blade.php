{{--
    One KPI tile.

    Wrapped in a link when the tile has a page behind it, because a number
    somebody wants to act on is a number they will try to click. Tiles with no
    route render as a plain div rather than a link that goes nowhere.

    Note what is not here: no percentage delta. See the head of
    config/dashboard.php for why the handover's "12.5% vs last month" is not
    coming back.
--}}
@php $tag = $kpi['route'] && \Illuminate\Support\Facades\Route::has($kpi['route']) ? 'a' : 'div'; @endphp

<{{ $tag }} class="kpi{{ $tag === 'a' ? ' kpi-link' : '' }}"
    @if ($tag === 'a') href="{{ route($kpi['route']) }}" @endif>

    <div class="kpi-ic {{ $kpi['tone'] }}" aria-hidden="true">
        @include('partials.nav-icon', ['icon' => $kpi['icon']])
    </div>

    <div class="kpi-body">
        <div class="kpi-lbl">{{ $kpi['label'] }}</div>
        <div class="kpi-val">{{ $kpi['value'] }}</div>
        <span class="kpi-sub">{{ $kpi['sub'] }}</span>
    </div>
</{{ $tag }}>
