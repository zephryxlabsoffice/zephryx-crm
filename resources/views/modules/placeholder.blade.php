@extends('layouts.app')

@section('title', $moduleLabel)
@section('page-heading', $moduleLabel)
@section('page-subheading', 'Not built yet.')

@section('content')
    <div class="card">
        <div class="card-body">
            @include('partials.notice', [
                'tone' => 'info',
                'title' => 'Placeholder',
                'message' => $moduleLabel.' has a navigation entry and a route so the shell can be '
                    .'reviewed as a whole, but the module itself has not been built. It is next in '
                    .'line when the roadmap reaches it.',
            ])
        </div>
    </div>
@endsection
