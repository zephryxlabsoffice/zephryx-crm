@extends('layouts.app')

@php
    use App\Http\Controllers\EodController;
    use App\Support\TaskPresenter as P;
    $itemsByTask = $report?->items->whereNotNull('task_id')->keyBy('task_id') ?? collect();
    $displayDate = \Illuminate\Support\Carbon::parse($date);
    $isToday = $displayDate->isToday();
    $hasOld = session()->hasOldInput();
    $extraRows = $hasOld
        ? collect(old('extra', []))
        : $extraItems->map(fn ($item) => [
            'project' => $item->project_id === null ? EodController::NO_PROJECT : (string) $item->project_id,
            'title' => $item->title,
            'status' => $item->status,
            'comment' => $item->comment,
        ]);
@endphp

@section('title', 'End of Day')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>End of Day</h1>
            <p>Tick what you worked on. This updates your tasks and is sent to your manager and team lead.</p>
        </div>

        <form class="hd-actions eod-date" method="GET" action="{{ route('tasks.eod') }}">
            <label class="form-field-lbl" for="eod-date">Report for</label>
            <input id="eod-date" type="date" name="date" value="{{ $date }}" min="{{ $earliestDate }}" max="{{ now()->toDateString() }}" data-auto-submit>
            <noscript><button class="btn btn-outline btn-sm" type="submit">Open</button></noscript>
        </form>
    </div>

    @include('tasks.partials.section-tabs', ['sectionTab' => 'eod'])

    @if ($report)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Already filed for '.$displayDate->format('l j F Y'),
            'message' => 'Sent on '.$report->submitted_at->format('d M Y, g:i A').'. Saving again updates it and tells the same people.',
        ])
    @elseif (! $isToday)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Filing for an earlier day: '.$displayDate->format('l j F Y'),
            'message' => 'It will show as filed late. A task only changes if no later report mentions it.',
        ])
    @endif

    @error('items')
        @include('partials.notice', ['tone' => 'warning', 'message' => $message])
    @enderror
    @error('assigned')
        @include('partials.notice', ['tone' => 'warning', 'message' => $message])
    @enderror

    <form method="POST" action="{{ route('tasks.eod.save') }}" data-eod-form>
        @csrf
        <input type="hidden" name="report_date" value="{{ $date }}">

        <section class="tasks-grid eod-grid">
            <div class="eod-main">
                <div class="card">
                    <div class="section-hd"><span class="an-step-no">1</span> Tasks assigned to you</div>

                    <div class="eod-rows">
                        @forelse ($assignedTasks as $task)
                            @php
                                $item = $itemsByTask->get($task->id);
                                $isTicked = $hasOld ? (bool) old("assigned.{$task->id}.checked") : $item !== null;
                                $chosen = old("assigned.{$task->id}.status", $item?->status ?? 'completed');
                            @endphp
                            <div class="eod-row" data-eod-row>
                                <label class="eod-row-main">
                                    <input type="checkbox" name="assigned[{{ $task->id }}][checked]" value="1" data-eod-tick @checked($isTicked)>
                                    <span class="eod-row-text">
                                        <strong>{{ $task->name }}</strong>
                                        <span>{{ $task->project?->name ?? 'No project' }} · due {{ $task->due_on->format('d M Y') }}</span>
                                    </span>
                                </label>

                                <div class="eod-row-fields" data-eod-fields>
                                    <div class="form-field">
                                        <label class="form-field-lbl" for="eod-status-{{ $task->id }}">Status</label>
                                        <select id="eod-status-{{ $task->id }}" name="assigned[{{ $task->id }}][status]">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status }}" @selected($chosen === $status)>{{ P::status($status)['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label class="form-field-lbl" for="eod-comment-{{ $task->id }}">Comments <span class="an-optional">(optional)</span></label>
                                        <input id="eod-comment-{{ $task->id }}" type="text" name="assigned[{{ $task->id }}][comment]" maxlength="1000" value="{{ old("assigned.{$task->id}.comment", $item?->comment) }}">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="rail-empty">No open tasks are assigned to you for this day.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd"><span class="an-step-no">2</span> Work that was not assigned</div>

                    <div class="eod-extras" data-eod-extras data-eod-next="{{ $extraRows->keys()->max() + 1 }}">
                        @foreach ($extraRows as $index => $row)
                            @include('tasks.eod.partials.extra-row', ['index' => $index, 'row' => $row])
                        @endforeach
                    </div>

                    <div class="eod-add">
                        <button class="btn btn-outline" type="button" data-eod-add>Add Unassigned Task</button>
                    </div>

                    <template data-eod-template>
                        @include('tasks.eod.partials.extra-row', ['index' => '__INDEX__', 'row' => ['status' => 'completed']])
                    </template>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd"><strong>Sent to</strong></div>

                    @if ($alwaysSentTo->isEmpty())
                        <p class="rail-empty">Nobody yet. It also goes to the manager of any project you add.</p>
                    @else
                        <ul class="eod-people">
                            @foreach ($alwaysSentTo as $person)
                                <li>{{ $person->user?->name }}</li>
                            @endforeach
                        </ul>
                        <p class="eod-note">Plus the manager and team lead of any task or project you add.</p>
                    @endif

                    <div class="an-submit">
                        <button class="btn btn-primary" type="submit">{{ $report ? 'Update report' : 'Send report' }}</button>
                    </div>
                </section>

                @if ($recentReports->isNotEmpty())
                    <section class="rail-card">
                        <div class="rail-hd"><strong>Your recent reports</strong></div>
                        <div class="rail-list">
                            @foreach ($recentReports as $recent)
                                <a class="rail-row" href="{{ route('tasks.eod.show', ['eod' => $recent->id]) }}">
                                    <div class="rail-body">
                                        <strong>{{ $recent->report_date->format('D d M Y') }}</strong>
                                        <span>{{ $recent->items_count }} {{ \Illuminate\Support\Str::plural('item', $recent->items_count) }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </section>
    </form>
@endsection
