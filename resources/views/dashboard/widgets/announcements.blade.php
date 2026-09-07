@php use App\Support\AnnouncementPresenter as AN; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Announcements</strong>
        <a class="dash-link" href="{{ route('announcements.index') }}">Board</a>
    </div>

    @if ($w['items']->isEmpty())
        <p class="rail-empty">Nothing on the board.</p>
    @else
        <div class="rail-list">
            @foreach ($w['items'] as $item)
                <a class="rail-row" href="{{ route('announcements.show', $item['id']) }}">
                    <div class="rail-body">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ AN::category($item['category'])['label'] }}</span>
                    </div>
                    @if ($item['pinned'])
                        <span class="rail-time">Pinned</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</section>
