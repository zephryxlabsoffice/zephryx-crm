@extends('errors.layout')

@section('title', 'Something went wrong')
@section('code', '500')
@section('tone', 'tone-danger')

@section('mark')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>
    </svg>
@endsection

@section('heading', 'Something went wrong at our end')

{{--
    No exception message, no stack trace, no file path. In production those
    would leak the application's internals to whoever tripped the error; in
    development Laravel shows its own debug page instead of this one, so
    nothing useful is lost either way.
--}}
@section('message')
    This is our fault, not yours. The problem has been recorded. Try again in a
    moment, and let an administrator know if it keeps happening.
@endsection
