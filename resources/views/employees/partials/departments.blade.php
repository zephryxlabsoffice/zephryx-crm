@php use App\Support\EmployeePresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Employees by Department</strong>
        <a class="card-link" href="{{ route('teams.index') }}">View all</a>
    </div>

    @if ($breakdown === [])
        <p class="rail-empty">No departments to show yet.</p>
    @else
        <div class="dept-grid">
            {{--
                The donut is decorative: it is aria-hidden and every figure it
                encodes is in the legend below, which is a real list. A chart
                that is the only source of its own numbers is unreadable to a
                screen reader and to anyone who cannot separate the colours.

                Arcs are computed from the data, not drawn by hand — the
                handover's SVG had fixed segments that would not have matched
                real headcounts.
            --}}
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="{{ $donutRadius }}" stroke-width="18"/>
                    @php $offset = 0; @endphp
                    @foreach ($breakdown as $index => $dept)
                        @php $seg = P::donutSegment($dept['share'], $offset, $circumference); @endphp
                        <circle class="seg {{ P::dot($index) }}"
                                cx="66" cy="66" r="{{ $donutRadius }}"
                                stroke="var(--dot)"
                                stroke-dasharray="{{ $seg['dash'] }}"
                                stroke-dashoffset="{{ $seg['offset'] }}"/>
                        @php $offset += $dept['share']; @endphp
                    @endforeach
                </svg>
                <div class="donut-center">
                    <b>{{ number_format($stats['total']) }}</b>
                    <span>Total</span>
                </div>
            </div>

            <ul class="dept-legend">
                @foreach ($breakdown as $index => $dept)
                    <li class="dept-leg {{ P::dot($index) }}">
                        <strong>{{ $dept['name'] }}</strong>
                        <em>{{ $dept['count'] }} ({{ $dept['share'] }}%)</em>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
