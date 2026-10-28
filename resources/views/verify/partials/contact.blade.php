{{-- The enquiry buttons and the company's registration IDs, set on the verify dashboard. --}}
@if ($registrations->isNotEmpty())
    <section class="vf-regs" aria-label="Company registration">
        <p class="vf-contact-hd">{{ config('zephryx.brand.name') }} registration</p>
        <dl class="vf-facts">
            @foreach ($registrations as $registration)
                <div>
                    <dt>{{ $registration->label() }}</dt>
                    <dd>
                        <span class="vf-mono">{{ $registration->number }}</span>
                        @if ($registration->document_path)
                            · <a href="{{ route('verify.registration', $registration) }}" target="_blank" rel="noopener">View</a>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif

@if ($phone || $email)
    <section class="vf-contact">
        <p class="vf-contact-hd">Questions about this verification?</p>

        <div class="vf-contact-actions">
            @if ($phone)
                <a class="btn btn-outline" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Call
                </a>
            @endif
            @if ($email)
                <a class="btn btn-outline" href="mailto:{{ $email }}?subject={{ rawurlencode('Employee verification enquiry') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>
                    </svg>
                    Email
                </a>
            @endif
        </div>
    </section>
@endif
