<section class="rail-card">
    <div class="rail-hd">
        <strong>Task Timeline</strong>
    </div>

    @if ($timeline === [])
        <p class="rail-empty">Nothing recorded yet.</p>
    @else
        {{-- An ordered list, because the sequence is the meaning. Every entry
             here has already happened, so all of them are marked done — the
             component also supports pending steps for approval chains. --}}
        <ol class="timeline">
            @foreach ($timeline as $event)
                <li class="timeline-row is-done">
                    <span class="timeline-time">{{ $event['when'] }}</span>
                    <strong>{{ $event['what'] }}</strong>
                    <span class="timeline-by">by {{ $event['who'] }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
