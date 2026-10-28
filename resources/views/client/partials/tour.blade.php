{{--
    The client portal tour (App\Support\PortalTour).

    No script: the CSP refuses inline script, and a tour should never be the
    thing that breaks a page. The steps are radio buttons - each Next/Back is
    a <label> for the next one - and CSS shows the step whose radio is checked.
    The <dialog> closes with a method="dialog" form.
--}}
@php $steps = \App\Support\PortalTour::steps(); $last = count($steps); @endphp

<dialog class="launch tour" open aria-labelledby="tour-title">
    <div class="launch-card tour-card">
        @foreach ($steps as $i => $step)
            <input class="tour-radio" type="radio" name="tour-step" id="tour-step-{{ $i + 1 }}" @checked($i === 0)>
        @endforeach

        <div class="tour-steps">
            @foreach ($steps as $i => $step)
                @php $n = $i + 1; @endphp
                <section class="tour-step tour-step-{{ $n }}">
                    <div class="launch-mark tour-mark" aria-hidden="true">@include('partials.nav-icon', ['icon' => $step['icon']])</div>
                    <p class="tour-count">Step {{ $n }} of {{ $last }}</p>
                    <h2 class="launch-title" @if ($n === 1) id="tour-title" @endif>{{ $step['title'] }}</h2>
                    <p class="launch-text">{{ $step['text'] }}</p>

                    <div class="tour-actions">
                        @if ($n > 1)
                            <label class="btn btn-outline" for="tour-step-{{ $n - 1 }}">Back</label>
                        @else
                            <form method="dialog"><button class="btn btn-ghost" type="submit">Skip</button></form>
                        @endif

                        @if ($n < $last)
                            <label class="btn btn-primary" for="tour-step-{{ $n + 1 }}">Next</label>
                        @else
                            <form method="dialog"><button class="btn btn-primary" type="submit">Done</button></form>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <div class="tour-dots" aria-hidden="true">
            @foreach ($steps as $i => $step)
                <label class="tour-dot tour-dot-{{ $i + 1 }}" for="tour-step-{{ $i + 1 }}"></label>
            @endforeach
        </div>
    </div>
</dialog>
