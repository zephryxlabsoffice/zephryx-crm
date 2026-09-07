{{--
    One stat tile in the client portal.

    A link when there is a page behind the number, a plain div when there is
    not — a figure somebody wants to act on is a figure they will try to click.

    No percentage delta, here or anywhere in this portal. See the head of
    config/dashboard.php for the argument; it applies identically on this side,
    with the extra point that a client has even less context for "12.5% vs last
    month" than we do.
--}}
@php
    $to = ($route ?? null) && \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;
    $tag = $to ? 'a' : 'div';
@endphp

<{{ $tag }} class="kpi{{ $to ? ' kpi-link' : '' }}" @if ($to) href="{{ $to }}" @endif>
    <div class="kpi-ic {{ $tone ?? 'tone-soft' }}" aria-hidden="true">
        @include('partials.nav-icon', ['icon' => $icon])
    </div>

    <div class="kpi-body">
        <div class="kpi-lbl">{{ $label }}</div>
        <div class="kpi-val">{{ $value }}</div>
        <span class="kpi-sub">{{ $sub }}</span>
    </div>
</{{ $tag }}>
