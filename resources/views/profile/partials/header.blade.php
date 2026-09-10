@php
    use App\Support\Avatar;
    use App\Support\ProfilePolicy;
    use App\Support\ProfilePresenter as P;
@endphp

{{--
    Identity, and the two columns of things the person does not control.

    The layout is the handover's and it is a good one — who you are on the left,
    what the company records about you on the right. What changed is that the
    right-hand columns now say they are read-only and why, rather than sitting
    silently next to editable fields and leaving somebody to work out which is
    which.
--}}
<section class="card pf-header">
    <div class="pf-identity">
        {{--
            The photo where there is one, initials where there is not.

            Initials are the permanent fallback rather than a placeholder image:
            they are always available, always legible, and never a broken icon.
            The photo is served through a route because it lives on the private
            disk — there is no public URL for it and there is not meant to be.
        --}}
        @if ($profile['photo_path'])
            <img class="avatar avatar-xl pf-photo" src="{{ route('profile.photo.show') }}"
                 alt="" width="96" height="96">
        @else
            <span class="avatar avatar-xl {{ Avatar::tint($profile['name']) }}" aria-hidden="true">
                {{ Avatar::initials($profile['name']) }}
            </span>
        @endif

        <div class="pf-identity-body">
            <div class="pf-name-row">
                <h2 class="pf-name">{{ $profile['name'] }}</h2>
                <span class="pill pill-blue">{{ $profile['designation'] }}</span>
            </div>

            <ul class="pf-contact">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                    {{ $profile['email'] }}
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ $profile['phone'] ?? 'No number on record' }}
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Joined {{ P::date($profile['joined']) }} · {{ P::tenure($profile['joined']) }}
                </li>
            </ul>
        </div>
    </div>

    <div class="pf-facts">
        <div class="pf-fact-col">
            <div class="pf-fact-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9l9-6 9 6"/><path d="M5 9v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/>
                </svg>
                <strong>Your employment record</strong>
                {{-- Said once, at the top of the column, rather than repeated as
                     a padlock on six rows. --}}
                <span class="pf-owned">HR keeps this</span>
            </div>

            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('employee_id') }}</span>
                <span class="stat-value">{{ $profile['user_id'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('department') }}</span>
                <span class="stat-value">{{ $profile['department'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('designation') }}</span>
                <span class="stat-value">{{ $profile['designation'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('reports_to') }}</span>
                <span class="stat-value">
                    {{ $profile['manager_record']['name'] ?? 'Nobody on record' }}
                </span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('dob') }}</span>
                {{-- A day and a month. Never the year — the same rule the
                     birthday board holds to, and a screenshot of this page is
                     still a screenshot. --}}
                <span class="stat-value">{{ P::dayAndMonth($profile['dob']) }}</span>
            </div>
        </div>

        <div class="pf-fact-col">
            <div class="pf-fact-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <strong>Your sign-in</strong>
                <span class="pf-owned">Recorded automatically</span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Username</span>
                <span class="stat-value">{{ $profile['username'] ?? $profile['email'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('email_verified') }}</span>
                <span class="stat-value">
                    @if ($profile['email_verified_at'] !== null)
                        <span class="pf-verified">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Verified
                        </span>
                    @else
                        <span class="is-warn">Not verified</span>
                    @endif
                </span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ ProfilePolicy::labelOf('last_login') }}</span>
                <span class="stat-value">{{ P::dateTime($profile['last_login_at']) }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Password</span>
                <span class="stat-value stat-value-quiet">{{ P::passwordAge($profile['password_changed_at']) }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Status</span>
                <span class="stat-value">{{ ucfirst(str_replace('_', ' ', $profile['status'])) }}</span>
            </div>
        </div>
    </div>
</section>
