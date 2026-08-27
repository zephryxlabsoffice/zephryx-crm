@php use App\Support\Chart; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Project Status</strong>
    </div>

    @if ($breakdown === [])
        <p class="rail-empty">No projects to break down yet.</p>
    @else
        <div class="chart-stack">
            {{-- The donut is aria-hidden; the legend beside it is a real list,
                 so the figures do not depend on telling the colours apart. --}}
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="{{ $donutRadius }}" stroke-width="18"/>
                    @php $offset = 0; @endphp
                    @foreach ($breakdown as $index => $slice)
                        @php $seg = Chart::donutSegment($slice['share'], $offset, $circumference); @endphp
                        <circle class="seg {{ Chart::dot($index) }}"
                                cx="66" cy="66" r="{{ $donutRadius }}"
                                stroke="var(--dot)"
                                stroke-dasharray="{{ $seg['dash'] }}"
                                stroke-dashoffset="{{ $seg['offset'] }}"/>
                        @php $offset += $slice['share']; @endphp
                    @endforeach
                </svg>
                <div class="donut-center">
                    <b>{{ number_format($stats['total']) }}</b>
                    <span>Total</span>
                </div>
            </div>

            <ul class="chart-legend">
                @foreach ($breakdown as $index => $slice)
                    <li class="chart-leg {{ Chart::dot($index) }}">
                        <strong>{{ $slice['name'] }}</strong>
                        <em>{{ $slice['count'] }} ({{ $slice['share'] }}%)</em>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
