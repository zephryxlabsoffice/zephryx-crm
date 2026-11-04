@extends('layouts.app')

@php
    use App\Support\TaskPresenter as P;
    $assigned = $report->items->filter(fn ($item) => $item->task_id !== null);
    $extras = $report->items->filter(fn ($item) => $item->task_id === null);
    $name = $report->employee->user?->name ?? 'Unknown';
@endphp

@section('title', 'End of Day: '.$report->report_date->format('d M Y'))

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $isMine ? route('tasks.eod') : route('tasks.eod.team', ['date' => $report->report_date->toDateString()]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $isMine ? 'End of Day' : 'Team Reports' }}
            </a>
            <h1>{{ $name }}: {{ $report->report_date->format('l j F Y') }}</h1>
            <p>
                Sent {{ $report->submitted_at->format('d M Y, g:i A') }}
                @if ($report->wasFiledLate()) · <strong>filed late</strong> @endif
            </p>
        </div>

        @if ($isMine)
            <div class="hd-actions">
                <a class="btn btn-outline" href="{{ route('tasks.eod', ['date' => $report->report_date->toDateString()]) }}">Edit</a>
            </div>
        @endif
    </div>

    <section class="tasks-grid eod-grid">
        <div class="eod-main">
            @foreach ([['Assigned tasks', $assigned], ['Work that was not assigned', $extras]] as [$heading, $items])
                @if ($items->isNotEmpty())
                    <div class="card">
                        <div class="section-hd">{{ $heading }}</div>
                        <ul class="eod-lines">
                            @foreach ($items as $item)
                                @php $pill = P::status($item->status); @endphp
                                <li>
                                    <div class="eod-row-text">
                                        <strong>
                                            @if ($item->task)
                                                <a href="{{ route('tasks.show', ['task' => $item->task->reference]) }}">{{ $item->title }}</a>
                                            @else
                                                {{ $item->title }}
                                            @endif
                                        </strong>
                                        <span>{{ $item->project?->name ?? 'No project' }}</span>
                                        @if ($item->comment)
                                            <span class="eod-comment">{{ $item->comment }}</span>
                                        @endif
                                    </div>
                                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd"><strong>Sent to</strong></div>
                @if ($report->recipients->isEmpty())
                    <p class="rail-empty">Nobody. No manager or team lead is set for this person or their work.</p>
                @else
                    <ul class="eod-people">
                        @foreach ($report->recipients as $person)
                            <li>{{ $person->user?->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </section>
@endsection
