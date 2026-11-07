@extends('layouts.app')

@section('title', 'Diagnostics')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Diagnostics</h1>
            <p>
                What went wrong for people: server errors, expired pages, and actions the
                system turned down. Each has a code they see and can quote on a ticket. Kept
                {{ $keepDays }} days.
            </p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('admin.diagnostics.download') }}">Download log</a>
        </div>
    </div>

    @if (! $ready)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'Not switched on yet',
            'message' => 'Apply the pending changes in Admin > Updates. The download still includes the server log.',
        ])
    @endif

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">Latest 200</span>
        </div>

        <div class="card-body-table">
            <table class="data-table data-table-stack">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Code</th>
                        <th scope="col">Who</th>
                        <th scope="col">Page</th>
                        <th scope="col">What happened</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td class="cell-tight" data-label="When">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d M, g:i A') }}</td>
                            <td class="cell-tight" data-label="Code">
                                <strong>{{ $event->code }}</strong>
                                <span class="pill {{ $event->kind === 'error' ? 'pill-red' : 'pill-amber' }}">{{ ucfirst($event->kind) }}</span>
                            </td>
                            <td data-label="Who">{{ $event->user_name ?? 'Not signed in' }}</td>
                            <td data-label="Page">{{ $event->method }} {{ $event->path }}</td>
                            <td data-label="What happened">
                                {{ $event->message }}
                                @if ($event->location)
                                    <span class="prose-quiet">{{ $event->location }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="table-empty"><strong>Nothing has gone wrong yet.</strong></div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
