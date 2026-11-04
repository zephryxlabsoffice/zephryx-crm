@extends('layouts.app')

@section('title', 'End of Day')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>End of Day</h1>
            <p>Your daily report of what you worked on.</p>
        </div>
    </div>

    @include('tasks.partials.section-tabs', ['sectionTab' => 'none'])

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'End of Day starts on '.$startsOn->format('l j F Y'),
        'message' => 'Nothing needs filing until then. Until that day, finish tasks from the task page as usual.',
    ])
@endsection
