@extends('layouts.app')

@php use App\Support\Demo\DemoAudit; @endphp

@section('title', 'Audit Log')

@section('content')
    <div class="page-hd">
        <h1>Audit log</h1>
        <p>Authentication, permission changes, and everything done in this panel.</p>
    </div>

    {{--
        No delete, no clear, no bulk actions, and no export that quietly drops
        rows. An audit log with a delete button is not an audit log, and this
        is the account whose actions most need the record — its own log being
        unalterable by it is the point.
    --}}
    <nav class="tabs" aria-label="Audit filters">
        <a class="tab @if ($kind === null) active @endif"
           href="{{ route('admin.audit.index') }}"
           @if ($kind === null) aria-current="page" @endif>
            All
            <span class="tab-count">{{ $counts['total'] }}</span>
        </a>

        @foreach ($kinds as $key => $meta)
            <a class="tab @if ($kind === $key) active @endif"
               href="{{ route('admin.audit.index', ['kind' => $key]) }}"
               @if ($kind === $key) aria-current="page" @endif>
                {{ $meta['label'] }}
                <span class="tab-count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <section class="card table-card">
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">What happened</th>
                        <th scope="col">Who</th>
                        <th scope="col">Kind</th>
                        <th scope="col">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php $meta = DemoAudit::kind($entry['kind']); @endphp
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('admin.audit.show', $entry['id']) }}">
                                    <strong>{{ $entry['action'] }}</strong>
                                    {{-- The effect, not only the value. It is
                                         the reason somebody opens this log. --}}
                                    @if ($entry['after'])
                                        <span class="dash-sub">{{ $entry['after'] }}</span>
                                    @endif
                                </a>
                            </td>
                            <td class="cell-tight">
                                {{ $entry['actor'] }}
                                @if (($entry['failed'] ?? false))
                                    <span class="dash-sub"><span class="is-overdue">Refused</span></span>
                                @endif
                            </td>
                            <td class="cell-tight"><span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                            <td class="cell-tight">{{ $entry['at']->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="table-empty">
                                    <strong>Nothing recorded</strong>
                                    <span>Entries appear here as things happen.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entries->total() > 0)
            @include('partials.pagination', ['paginator' => $entries, 'unit' => 'entries'])
        @endif
    </section>
@endsection
