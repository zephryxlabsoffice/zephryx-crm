@extends('layouts.verify')

@section('title', 'Verify dashboard')
@section('main-class', 'vf-main-wide')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>ID card verification</h1>
            <p>Everybody on file in the CRM, how often their card is checked, and the QR for each card.</p>
        </div>
        <div class="hd-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline" type="submit">Sign out</button>
            </form>
        </div>
    </div>

    <section class="kpi-row" aria-label="Summary">
        <div class="kpi">
            <div class="kpi-ic tone-soft" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'employees'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">Working now</div>
                <div class="kpi-val">{{ $stats['working'] }}</div>
                <span class="kpi-sub">{{ $stats['full_time'] }} full-time · {{ $stats['interns'] }} interns · {{ $stats['freelancers'] }} freelancers</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-ic tone-soft" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'reports'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">QR scans</div>
                <div class="kpi-val">{{ $stats['qr_scans'] }}</div>
                <span class="kpi-sub">{{ $stats['qr_scans_30'] }} in the last 30 days</span>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-ic tone-soft" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'tickets'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">Searches</div>
                <div class="kpi-val">{{ $stats['searches'] }}</div>
                <span class="kpi-sub">Employee IDs typed on the site</span>
            </div>
        </div>
    </section>

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">People</span>
            <form class="vf-filter" method="GET" action="{{ route('verify.admin') }}">
                <label class="sr-only" for="vf-q">Search</label>
                <input id="vf-q" name="q" type="search" placeholder="Name or ID" value="{{ $search }}">
                <button class="chip-btn" type="submit">Search</button>
            </form>
        </div>

        <div class="card-body-table">
            <table class="data-table data-table-stack">
                <thead>
                    <tr>
                        <th scope="col">Employee ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Engagement</th>
                        <th scope="col">Designation</th>
                        <th scope="col">Status</th>
                        <th scope="col">QR scans</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="vf-mono" data-label="ID">{{ $row['id'] }}</td>
                            <td data-label="Name">{{ $row['name'] }}</td>
                            <td data-label="Engagement">{{ $row['type'] }}</td>
                            <td data-label="Designation">{{ $row['designation'] ?? '-' }}</td>
                            <td data-label="Status">
                                @if ($row['working'])
                                    <span class="pill pill-green">Working</span>
                                @else
                                    <span class="pill pill-gray">Left{{ $row['left_on'] ? ' '.$row['left_on']->format('j M Y') : '' }}</span>
                                @endif
                            </td>
                            <td data-label="QR scans">{{ $row['qr'] }}</td>
                            <td class="vf-row-actions">
                                <a class="btn btn-ghost btn-sm" href="{{ \App\Http\Controllers\Verify\AdminController::cardUrl($row['id']) }}" target="_blank" rel="noopener">Open</a>
                                <a class="btn btn-primary btn-sm" href="{{ route('verify.admin.qr', $row['id']) }}">Download QR</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="vf-empty">Nobody matches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="vf-settings">
        <section class="card">
            <div class="card-hd"><span class="card-title">Enquiry buttons</span></div>
            <div class="card-body">
                <p class="prose-quiet">The Call and Email buttons on every verify page. Leave one blank to hide it.</p>
                <form method="POST" action="{{ route('verify.admin.settings') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="vf-phone">Phone</label>
                            <input id="vf-phone" name="phone" type="tel" maxlength="30" placeholder="+91 98765 43210" value="{{ old('phone', $phone) }}">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-field-lbl" for="vf-email">Email</label>
                            <input id="vf-email" name="email" type="email" maxlength="190" placeholder="hr@zephryxlabs.in" value="{{ old('email', $email) }}">
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-hd"><span class="card-title">Company registration</span></div>
            <div class="card-body">
                <p class="prose-quiet">
                    @if ($registrationAt)
                        A PDF is on the verify pages, uploaded {{ \Illuminate\Support\Carbon::parse($registrationAt)->format('j M Y, g:i A') }}.
                        <a href="{{ route('verify.registration') }}" target="_blank" rel="noopener">Open it</a>.
                    @else
                        No PDF yet; the link is hidden until one is uploaded.
                    @endif
                </p>
                <form method="POST" action="{{ route('verify.admin.registration') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-field">
                        <label class="form-field-lbl" for="vf-reg">PDF, up to 10 MB</label>
                        <input id="vf-reg" name="registration" type="file" accept="application/pdf">
                        @error('registration') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Upload</button></div>
                </form>
                @if ($registrationAt)
                    <form method="POST" action="{{ route('verify.admin.registration') }}">
                        @csrf
                        <input type="hidden" name="remove" value="1">
                        <button class="btn btn-ghost btn-sm" type="submit">Remove the PDF</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
@endsection
