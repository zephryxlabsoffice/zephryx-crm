@php use App\Support\Chart; @endphp

{{--
    Where the book stands, by status.

    This donut deliberately does NOT use the shared cycling palette the way
    Employees and Teams do. Their categories are master data — departments —
    with no inherent meaning, so any colour will do. Invoice statuses already
    carry meaning in the pills a few centimetres to the left: green settled,
    red overdue, amber part-paid. A legend that coloured "Overdue" indigo
    because it happened to sort third would teach people that colour means
    nothing, which is exactly what §7 says categorical accents exist to avoid.

    So each segment takes its own status tone, and the legend matches the pill.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Where the book stands</strong>
    </div>

    @if ($mix === [])
        <p class="rail-empty">No invoices to break down yet.</p>
    @else
        <div class="chart-stack">
            {{-- aria-hidden: the legend below is the real, readable source of
                 these figures. A chart that is the only place its own numbers
                 appear is unreadable to a screen reader. --}}
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="{{ $donutRadius }}" stroke-width="18"/>
                    @php $offset = 0; @endphp
                    @foreach ($mix as $slice)
                        @php $seg = Chart::donutSegment($slice['share'], $offset, $circumference); @endphp
                        <circle class="seg seg-{{ $slice['status'] }}"
                                cx="66" cy="66" r="{{ $donutRadius }}"
                                stroke="var(--dot)"
                                stroke-dasharray="{{ $seg['dash'] }}"
                                stroke-dashoffset="{{ $seg['offset'] }}"/>
                        @php $offset += $slice['share']; @endphp
                    @endforeach
                </svg>
                <div class="donut-center">
                    {{-- "Total", not "Invoices": the donut's centre is about
                         38px wide, and the longer word overlaps the ring. It
                         also matches the Employees and Teams donuts. --}}
                    <b>{{ number_format($stats['total']) }}</b>
                    <span>Total</span>
                </div>
            </div>

            <ul class="chart-legend">
                @foreach ($mix as $slice)
                    <li class="chart-leg seg-{{ $slice['status'] }}">
                        <strong>{{ $slice['name'] }}</strong>
                        <em>{{ $slice['count'] }} ({{ $slice['share'] }}%)</em>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
