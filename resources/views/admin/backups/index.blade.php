@extends('layouts.app')

@section('title', 'Backups')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Backups</h1>
            <p>
                A copy of the whole database, taken every night and on demand. The last
                {{ $keepLocal }} stay on this server; the last {{ $keepDrive }} go to the
                Google Shared Drive, which survives losing the server.
            </p>
        </div>

        <div class="hd-actions">
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Back up now</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        @include('partials.notice', [
            'tone' => session('status_tone', 'success'),
            'message' => session('status'),
        ])
    @endif

    @php
        $lastAt = $last ? \Illuminate\Support\Carbon::parse($last['at']) : null;
        // A nightly job that has not run for two days has stopped, whatever
        // the reason. Saying so here is the only alarm there is.
        $stale = $lastAt === null || $lastAt->lt(now()->subHours(36));
    @endphp

    @if ($last === null)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'No backup has been taken yet',
            'message' => 'Take one now, and set up the nightly Cron Job in cPanel (see docs/DEPLOY.md).',
        ])
    @elseif ($stale)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'The last backup is more than a day old',
            'message' => 'Taken '.$lastAt->format('d M Y, g:i A').'. The nightly Cron Job may have stopped — check it in cPanel.',
        ])
    @elseif ($last['drive'] !== 'sent')
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'The last backup was not copied to Google Drive',
            'message' => 'Google Drive: '.$last['drive'].'. It exists on this server only.',
        ])
    @endif

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">On this server</span>
        </div>

        <div class="card-body-table">
            <table class="data-table data-table-stack">
                <thead>
                    <tr>
                        <th scope="col">Taken</th>
                        <th scope="col">File</th>
                        <th scope="col">Size</th>
                        <th scope="col"><span class="sr-only">Download</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($files as $file)
                        <tr>
                            <td class="cell-tight" data-label="Taken">{{ $file['at']->format('d M Y, g:i A') }}</td>
                            <td data-label="File">{{ $file['name'] }}</td>
                            <td class="cell-tight" data-label="Size">{{ number_format($file['bytes'] / 1024, 1) }} KB</td>
                            <td class="cell-actions" data-label="Download">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.backups.download', ['file' => $file['name']]) }}">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="table-empty"><strong>No backups yet.</strong></div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-hd">
            <span class="card-title">Restoring</span>
        </div>
        <div class="card-body prose">
            <p>
                Each file is a gzipped SQL dump. To restore: cPanel → phpMyAdmin → choose the
                database → Import → pick the <strong>.sql.gz</strong> file. This replaces every
                table with the copy in the file.
            </p>
            <p>
                Some fields are encrypted with the server's APP_KEY, so a restore only works
                with the same key. Keep a copy of the server's <strong>.env</strong> file
                somewhere safe, apart from these backups.
            </p>
        </div>
    </section>
@endsection
