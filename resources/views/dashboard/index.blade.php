@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{--
        The dashboard is assembled, never written out.

        There is no list of widgets in this file and no `@if ($isHr)` anywhere
        in the module: the page is whatever the viewer's permissions add up to,
        resolved in config/dashboard.php and filtered by DashboardComposer. A
        widget added to the registry appears here without this file changing,
        which is the property that makes one dashboard viable where five
        hand-written ones were not (§2.4).
    --}}

    <div class="dash-hd">
        <div class="page-hd">
            <h1>{{ $greeting }}, {{ $firstName }}</h1>
            <p>{{ $today }}</p>
        </div>
    </div>

    @include('dashboard.partials.preview')

    @if ($kpis !== [])
        <section class="kpi-row" aria-label="Summary">
            @foreach ($kpis as $kpi)
                @include('dashboard.partials.kpi', ['kpi' => $kpi])
            @endforeach
        </section>
    @endif

    @if ($main === [] && $rail === [])
        {{-- Reachable: a role with `dashboard.view` and nothing else. Better an
             honest empty page than a grid of cards holding zeros. --}}
        <section class="card dash-empty">
            <div class="card-body">
                <h2>Nothing to show you yet</h2>
                <p>
                    This account can reach the dashboard but holds no permissions
                    that put anything on it. If that looks wrong, it is a question
                    for whoever set the account up.
                </p>
            </div>
        </section>
    @else
        <section class="dash-grid">
            <div class="dash-main">
                @foreach ($main as $widget)
                    @include('dashboard.widgets.'.$widget['key'], [
                        'widget' => $widget,
                        'w' => $data[$widget['key']],
                    ])
                @endforeach

                @if ($main === [])
                    <section class="card dash-quiet">
                        <div class="card-body">
                            <p>Nothing assigned to you across the modules you can reach.</p>
                        </div>
                    </section>
                @endif
            </div>

            {{--
                The rail is the Employee base (§2.2): my day, my leave, my
                payslip. A Mentor has no Employee base (§2.1) and so renders
                this column empty — which is the intended result, not a bug, and
                the fastest way to spot a rail widget carrying the wrong
                permission key.
            --}}
            @if ($rail !== [])
                <aside class="rail">
                    @foreach ($rail as $widget)
                        @include('dashboard.widgets.'.$widget['key'], [
                            'widget' => $widget,
                            'w' => $data[$widget['key']],
                        ])
                    @endforeach
                </aside>
            @endif
        </section>
    @endif
@endsection
