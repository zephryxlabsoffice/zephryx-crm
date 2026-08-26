{{--
    Pagination for any list.

    @include('partials.pagination', ['paginator' => $rows, 'unit' => 'clients'])

    Links, not buttons: each page is a real URL, so it can be bookmarked, opened
    in a new tab and reached with the browser's back button. The handover drew
    <button> elements with hardcoded page numbers.
--}}
@php
    $unit = $unit ?? 'results';
    // linkCollection() brackets the page numbers with its own Previous and
    // Next entries; this partial draws those itself, so they are trimmed off.
    $window = $paginator->onEachSide(1)->linkCollection()->slice(1, -1);
@endphp

<nav class="pagination" aria-label="Pagination">
    {{-- Built as one string rather than spread over template lines: Blade keeps
         the newlines, which turns the sentence into "Showing 1 to 7\n of 10". --}}
    <span class="pagination-meta">{{ $paginator->total() === 0
        ? 'No '.$unit
        : 'Showing '.$paginator->firstItem().' to '.$paginator->lastItem()
            .' of '.number_format($paginator->total()).' '.$unit }}</span>

    @if ($paginator->hasPages())
        <div class="pagination-pages">
            <a class="pg-btn"
               href="{{ $paginator->previousPageUrl() ?? '#' }}"
               @if (! $paginator->previousPageUrl()) aria-disabled="true" @endif
               rel="prev">
                <span class="sr-only">Previous page</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            @foreach ($window as $link)
                @if ($link['url'] === null)
                    <span class="pg-ellipsis" aria-hidden="true">{!! $link['label'] !!}</span>
                @else
                    <a class="pg-btn @if ($link['active']) active @endif"
                       href="{{ $link['url'] }}"
                       @if ($link['active']) aria-current="page" @endif>
                        <span class="sr-only">Page</span>{{ $link['label'] }}
                    </a>
                @endif
            @endforeach

            <a class="pg-btn"
               href="{{ $paginator->nextPageUrl() ?? '#' }}"
               @if (! $paginator->nextPageUrl()) aria-disabled="true" @endif
               rel="next">
                <span class="sr-only">Next page</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>
    @endif
</nav>
