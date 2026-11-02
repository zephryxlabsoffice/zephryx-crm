@extends('layouts.app')

@section('title', 'My Devices')

@section('content')
    <div class="page-hd">
        <h1>My Devices</h1>
        <p>Everywhere your account is signed in right now. If you do not recognise one, sign it out and reset your password.</p>
    </div>

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">Signed-in devices</span>

            <form method="POST" action="{{ route($routes.'reset-password') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Reset Password</button>
            </form>
        </div>

        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Device</th>
                        <th scope="col">Location</th>
                        <th scope="col">Last active</th>
                        <th scope="col" aria-label="Sign out"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        @php $isCurrent = $device->sign_in_token === $currentToken; @endphp
                        <tr>
                            <td>
                                <strong>{{ $device->device_name }}</strong>
                                @if ($isCurrent)
                                    <span class="pill pill-green">This device</span>
                                @endif
                            </td>
                            <td>
                                {{ $device->location ?? 'Location unknown' }}
                                @if ($device->ip_address)
                                    <br><span class="dash-quiet-meta">{{ $device->ip_address }}</span>
                                @endif
                            </td>
                            <td>{{ $isCurrent ? 'Now' : $device->last_seen_at->diffForHumans() }}</td>
                            <td>
                                {{-- This browser takes the ordinary log out, which also clears its own cookies. --}}
                                <form method="POST" action="{{ $isCurrent ? route('logout') : route($routes.'sign-out', $device->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Sign Out</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
