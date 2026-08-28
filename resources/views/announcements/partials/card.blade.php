@php
    use App\Support\AnnouncementPresenter as P;
    use App\Support\Avatar;
    $cat = P::category($announcement['category']);
    $isMilestone = $announcement['kind'] === 'milestone';
@endphp

{{--
    One announcement on the board.

    A milestone is drawn differently from an authored post — it has no author,
    no expiry worth stating and nothing to manage, and dressing it up as a
    written announcement would invite somebody to try editing it.
--}}
<article class="card an-card @if ($announcement['pinned']) is-pinned @endif @if ($isMilestone) is-milestone @endif">
    <div class="an-card-hd">
        @if ($isMilestone)
            <span class="avatar {{ Avatar::tint($announcement['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($announcement['employee_record']['name']) }}</span>
        @else
            <span class="an-card-ic {{ $cat['tone'] }}" aria-hidden="true">
                @include('partials.nav-icon', ['icon' => $cat['icon']])
            </span>
        @endif

        <div class="an-card-head">
            <h2 class="an-card-title">
                <a href="{{ route('announcements.show', ['announcement' => $announcement['id']]) }}">{{ $announcement['title'] }}</a>
            </h2>
            <p class="an-card-meta">
                <span class="an-chip {{ $cat['tone'] }}">{{ $cat['label'] }}</span>

                @if ($isMilestone)
                    {{-- No author: nobody wrote it. --}}
                    <span>{{ $announcement['employee_record']['department'] }}</span>
                @else
                    <span>{{ $announcement['author_record']['name'] ?? 'Unknown' }}</span>
                    <span>{{ P::ago($announcement['published_at']) }}</span>
                    @if ($announcement['audience'] !== 'everyone')
                        {{-- Worth saying when it is NOT everyone: a reader
                             should know whether the rest of the company saw
                             this too. --}}
                        <span class="an-audience">{{ P::audienceLabel($announcement) }}</span>
                    @endif
                @endif
            </p>
        </div>

        @if ($announcement['pinned'])
            <span class="an-pinned" title="Pinned to the top">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14l-1.7-5.1a2 2 0 0 1 .3-1.9L19 8V2H5v6l1.4 2a2 2 0 0 1 .3 1.9z"/>
                </svg>
                <span class="sr-only">Pinned</span>
            </span>
        @endif
    </div>

    <p class="an-card-body">{{ $announcement['body'] }}</p>

    @if (! $isMilestone)
        @php $runs = P::runsUntil($announcement); @endphp
        <p class="an-card-foot">
            <span class="{{ $runs['tone'] }}">{{ $runs['label'] }}</span>
        </p>
    @endif
</article>
