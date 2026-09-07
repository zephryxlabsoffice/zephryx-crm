@php use App\Support\TaskPresenter as TP; @endphp

{{--
    The viewer's own open tasks, soonest first — so overdue sorts to the top,
    because a late task is a negative number of days left.

    Completed tasks are not here. A lifetime "128 completed" is the handover's
    figure and it reads as zero for anybody who joined this year; what is being
    carried right now is the number somebody acts on.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Your tasks</span>
        <a class="card-link" href="{{ route('tasks.mine') }}">View all</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Nothing assigned to you right now.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Task</th>
                        <th scope="col">Status</th>
                        <th scope="col">Due</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $task)
                        @php $due = TP::due($task['due'], $task['status']); @endphp
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('tasks.show', $task['id']) }}">
                                    <strong>{{ $task['name'] }}</strong>
                                </a>
                            </td>
                            <td><span class="pill {{ TP::status($task['status'])['tone'] }}">{{ TP::status($task['status'])['label'] }}</span></td>
                            <td class="cell-tight">
                                <div class="due-cell">
                                    <strong>{{ TP::date($task['due']) }}</strong>
                                    <span class="{{ $due['state'] }}">{{ $due['label'] }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($w['total'] > $w['items']->count())
            <div class="card-body dash-more">
                <span>{{ $w['total'] - $w['items']->count() }} more open</span>
            </div>
        @endif
    @endif
</section>
