@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
    <div class="page-hd">
        <h1>Master data</h1>
        <p>The lists every other module chooses from.</p>
    </div>

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Nothing here is deleted',
        'message' => 'These rows are referenced by records that already exist. Removing one would not '
            .'remove that history — it would orphan it, leaving a department nobody can look up. Rows are '
            .'deactivated instead: they stop being offered for new records and keep answering for old ones.',
    ])

    <section class="qa-grid ad-lists">
        @foreach ($lists as $key => $list)
            <a class="qa-tile {{ $list['empty'] ? 'tone-warn' : '' }}" href="{{ route('admin.master.show', $key) }}">
                <span class="qa-ic" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'reports'])</span>
                <span>{{ $list['label'] }}</span>
                <span class="ad-list-count">
                    @if ($list['empty'])
                        Empty — nothing can be created that needs one
                    @else
                        {{ $list['active'] }} active
                        @if ($list['total'] > $list['active'])
                            · {{ $list['total'] - $list['active'] }} retired
                        @endif
                    @endif
                </span>
            </a>
        @endforeach
    </section>

    @foreach ($lists as $key => $list)
        <section class="card ad-list-card">
            <div class="card-hd">
                <span class="card-title">{{ $list['label'] }}</span>
                <a class="card-link" href="{{ route('admin.master.show', $key) }}">Open</a>
            </div>

            <div class="card-body">
                <p class="ad-group-note">{{ $list['note'] }}</p>
            </div>
        </section>
    @endforeach
@endsection
