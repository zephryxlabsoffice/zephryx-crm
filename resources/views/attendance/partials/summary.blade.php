@php
    use App\Support\Chart;

    // Geometry, not data. Kept here so the arcs and the legend below cannot be
    // computed from two different totals.
    $radius = 48;
    $circumference = 2 * M_PI * $radius;
@endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>{{ $heading }}</strong>
    </div>

    @if ($breakdown === [])
        <p class="rail-empty">Nothing to break down for this day.</p>
    @else
        <div class="chart-stack">
            {{--
                The donut is decorative: aria-hidden, and every figure it encodes
                is in the legend below as a real list. A chart that is the only
                source of its own numbers is unreadable to a screen reader and to
                anyone who cannot separate the colours.

                Arcs are computed from the counts. The handover's were fixed
                stroke-dasharray values typed in by hand, so they would not have
                matched a real day since the moment they were written — and it
                coloured them with style="--dot:#15A848", which our CSP blocks.
            --}}
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="{{ $radius }}" stroke-width="18"/>
                    @php $offset = 0; @endphp
                    @foreach ($breakdown as $segment)
                        @php $arc = Chart::donutSegment($segment['share'], $offset, $circumference); @endphp
                        <circle class="seg {{ $segment['dot'] }}"
                                cx="66" cy="66" r="{{ $radius }}"
                                stroke="var(--dot)"
                                stroke-dasharray="{{ $arc['dash'] }}"
                                stroke-dashoffset="{{ $arc['offset'] }}"/>
                        @php $offset += $segment['share']; @endphp
                    @endforeach
                </svg>
                <div class="donut-center">
                    <b>{{ $total }}</b>
                    <span>{{ $totalLabel }}</span>
                </div>
            </div>

            <ul class="chart-legend">
                @foreach ($breakdown as $segment)
                    <li class="chart-leg {{ $segment['dot'] }}">
                        <strong>{{ $segment['name'] }}</strong>
                        <em>{{ $segment['count'] }} ({{ $segment['share'] }}%)</em>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
