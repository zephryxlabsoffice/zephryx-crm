@php
    use App\Support\MeetingPresenter as P;
    $pill = P::status($meeting['status']);
    $timing = P::timing($meeting);
@endphp

<div class="card">
    <div class="mt-req-hd">
        <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
        @if ($meeting['status'] === P::SCHEDULED && $timing['tone'])
            <span class="mt-timing {{ $timing['tone'] }}">{{ $timing['label'] }}</span>
        @endif
    </div>

    <dl class="field-grid mt-req-grid">
        <div class="mt-field">
            <dt>When</dt>
            {{-- The zone abbreviation is not decoration: a client reading this
                 in another country needs to know which four o'clock is meant. --}}
            <dd>{{ P::when($meeting) }}</dd>
        </div>
        <div class="mt-field">
            <dt>Length</dt>
            <dd>{{ P::duration($meeting) }}</dd>
        </div>
        <div class="mt-field">
            <dt>Project</dt>
            <dd>
                @if ($meeting['project_record'])
                    <a class="card-link" href="{{ route('projects.show', ['project' => $meeting['project_record']['id']]) }}">{{ $meeting['project_record']['name'] }}</a>
                @else
                    Internal
                @endif
            </dd>
        </div>
        <div class="mt-field">
            <dt>Organiser</dt>
            <dd>{{ $meeting['organiser_record']['name'] ?? '—' }}</dd>
        </div>
    </dl>

    <div class="mt-agenda">
        <span class="mt-field-lbl">What it is about</span>
        <p>{{ $meeting['agenda'] }}</p>
    </div>
</div>
