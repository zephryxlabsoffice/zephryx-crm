@php use App\Support\ProjectPresenter as PP; @endphp

<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Projects</span>
        <a class="card-link" href="{{ route('projects.index') }}">View all</a>
    </div>

    @if ($w['items'] === [])
        <div class="card-body">
            <p class="rail-empty">No projects running.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $project)
                        @php $deadline = PP::deadline($project['deadline'], $project['status']); @endphp
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('projects.show', $project['id']) }}">
                                    <strong>{{ $project['name'] }}</strong>
                                </a>
                            </td>
                            <td>
                                <div class="progress-cell {{ PP::progressState($project['progress']) }}">
                                    <progress class="progress" value="{{ $project['progress'] }}" max="100"></progress>
                                    <span class="progress-pct">{{ $project['progress'] }}%</span>
                                </div>
                            </td>
                            <td class="cell-tight">
                                <div class="due-cell">
                                    <span class="{{ $deadline['state'] }}">{{ $deadline['label'] }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body dash-more">
            {{-- The counts that matter, stated rather than turned into a
                 percentage of a total nobody asked about. --}}
            <span>
                {{ $w['stats']['active'] }} active · {{ $w['stats']['on_hold'] }} on hold ·
                {{ $w['stats']['overdue'] }} past the deadline
            </span>
        </div>
    @endif
</section>
