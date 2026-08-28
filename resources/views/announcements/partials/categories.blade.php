{{--
    Categories, counted from the board itself.

    Every figure is computed, so a count can never disagree with the list
    beneath it — the handover's were written in (7 / 6 / 8 / 5 / 4 / 6). A
    category with nothing live in it is left out entirely rather than shown as a
    zero: an empty filter is a control that does nothing.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Categories</strong>
    </div>

    <ul class="an-cats">
        <li>
            <a class="an-cat @if (! $category) is-current @endif" href="{{ route('announcements.index') }}"
               @if (! $category) aria-current="page" @endif>
                <span class="an-cat-name">Everything</span>
                <span class="an-cat-count">{{ $boardTotal }}</span>
            </a>
        </li>

        @foreach ($categories as $cat)
            <li>
                <a class="an-cat @if ($category === $cat['key']) is-current @endif"
                   href="{{ route('announcements.index', ['category' => $cat['key']]) }}"
                   @if ($category === $cat['key']) aria-current="page" @endif>
                    <span class="an-dot {{ $cat['tone'] }}" aria-hidden="true"></span>
                    <span class="an-cat-name">{{ $cat['label'] }}</span>
                    <span class="an-cat-count">{{ $cat['count'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
