{{--
    Where somebody lives, for whoever holds `employees.identifiers`.

    Unmasked, unlike the card below it. An address is not a credential: nothing
    is protected by showing half of it, and a half-shown address is one HR
    cannot check against the photocopy in the file. The gate is the whole
    control — `$addresses` is null for anybody else and the controller never
    loaded the row.

    Rendered with `nl2br` and escaped first, because an address is three or four
    lines and a single run-on line is the version nobody can check.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 10.5 12 3l9 7.5V21H3z"/>
            <path d="M9 21v-7h6v7"/>
        </svg>
        How to reach them, and where they live
    </div>

    <dl class="field-grid att-record-grid">
        <div class="lv-field">
            <dt class="lv-field-lbl">Phone</dt>
            <dd>{{ $addresses['phone'] ?: 'Not recorded' }}</dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Personal email</dt>
            {{-- Labelled as personal so nobody mistakes it for the sign-in
                 address, which is on the card above this one. --}}
            <dd>{{ $addresses['personal_email'] ?: 'Not recorded' }}</dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Current address</dt>
            <dd>
                @if ($addresses['current'])
                    {!! nl2br(e($addresses['current'])) !!}
                @else
                    Not recorded
                @endif
            </dd>
        </div>

        <div class="lv-field">
            <dt class="lv-field-lbl">Permanent address</dt>
            <dd>
                @if ($addresses['permanent'])
                    {!! nl2br(e($addresses['permanent'])) !!}
                @else
                    {{-- Named as a gap rather than left blank: it is the address
                         on the ID proof, and HR chases the ones that are missing. --}}
                    Not recorded
                @endif
            </dd>
        </div>
    </dl>
</div>
