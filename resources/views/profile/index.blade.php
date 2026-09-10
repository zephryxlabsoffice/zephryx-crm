@extends('profile.partials.layout')

@php use App\Support\ProfilePolicy; @endphp

@section('title', 'My Profile')

@section('panel')
    <form class="pf-form" method="POST" action="{{ route('profile.update') }}">
        @csrf

        {{--
            Everything in this form is the person's own (ProfilePolicy::SELF).

            Nothing HR owns appears as an input anywhere on it — name,
            department, designation, reporting line, date of birth and role are
            in the header card above, shown and labelled as HR's. The handover
            had three of those as editable text boxes in this exact form.
        --}}
        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                About you
            </div>

            <div class="form-grid pf-form-grid">
                <div class="form-field">
                    <label class="form-field-lbl" for="pf-phone">{{ ProfilePolicy::labelOf('phone') }}</label>
                    {{-- A plain tel field. The handover had a country-code
                         picker with a flag: the company has one office in one
                         country, and a dropdown with a single meaningful entry
                         teaches people the controls are decorative. --}}
                    {{-- `old()` over the stored value throughout this form: a
                         validation error must not throw away the other ten
                         fields somebody had just finished typing. --}}
                    <input id="pf-phone" name="phone" type="tel" value="{{ old('phone', $profile['phone']) }}" placeholder="+91 …">
                    <span class="pay-hint">Colleagues can see this. It is not published outside the company.</span>
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-nationality">{{ ProfilePolicy::labelOf('nationality') }}</label>
                    <input id="pf-nationality" name="nationality" type="text" value="{{ old('nationality', $profile['nationality']) }}">
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-gender">{{ ProfilePolicy::labelOf('gender') }}</label>
                    {{-- "Prefer not to say" is first and is a real stored
                         answer, not a blank. Somebody who does not want to
                         state this should not have to pick the least wrong
                         option from a list they did not write. --}}
                    <select id="pf-gender" name="gender">
                        @foreach ($options['gender'] as $option)
                            <option value="{{ $option }}" @selected(old('gender', $profile['gender']) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-marital">{{ ProfilePolicy::labelOf('marital_status') }}</label>
                    <select id="pf-marital" name="marital_status">
                        @foreach ($options['marital_status'] as $option)
                            <option value="{{ $option }}" @selected(old('marital_status', $profile['marital_status']) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field pf-form-wide">
                    <label class="form-field-lbl" for="pf-languages">{{ ProfilePolicy::labelOf('languages') }}</label>
                    {{-- A text field, not the handover's removable chips. Chips
                         need JavaScript to add one, and ours is a comma — which
                         works with the keyboard, works with JavaScript off, and
                         is one thing to validate. --}}
                    <input id="pf-languages" name="languages" type="text"
                           value="{{ old('languages', implode(', ', $profile['languages'])) }}"
                           placeholder="English, Hindi, Bengali">
                    <span class="pay-hint">Separate them with commas.</span>
                </div>

                <div class="form-field pf-form-wide">
                    <label class="form-field-lbl" for="pf-address">{{ ProfilePolicy::labelOf('address') }}</label>
                    <textarea id="pf-address" name="address" rows="3">{{ old('address', $profile['address']) }}</textarea>
                    {{-- Says who reads it, before somebody types their home
                         address into it. Same rule as the leave reason. --}}
                    <span class="pay-hint">Held for your employment record. Seen by HR and the owner, and on no list of people.</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                    <path d="M12 9v4M12 17h.01"/>
                </svg>
                Emergency contact
            </div>

            <div class="prose prose-quiet pf-section-note">
                <p>
                    Used if something happens to you at work, and for nothing
                    else. Whoever you name here is not told they have been named,
                    so it is worth telling them yourself.
                </p>
            </div>

            <div class="form-grid pf-form-grid">
                <div class="form-field">
                    <label class="form-field-lbl" for="pf-emg-name">{{ ProfilePolicy::labelOf('emergency_name') }}</label>
                    <input id="pf-emg-name" name="emergency_name" type="text" value="{{ old('emergency_name', $profile['emergency_name']) }}">
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-emg-rel">{{ ProfilePolicy::labelOf('emergency_relationship') }}</label>
                    <input id="pf-emg-rel" name="emergency_relationship" type="text" value="{{ old('emergency_relationship', $profile['emergency_relationship']) }}">
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-emg-phone">{{ ProfilePolicy::labelOf('emergency_phone') }}</label>
                    <input id="pf-emg-phone" name="emergency_phone" type="tel" value="{{ old('emergency_phone', $profile['emergency_phone']) }}" placeholder="+91 …">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2-6.3-4.6-6.3 4.6L7.9 13.8 2 9.4h7.6z"/>
                </svg>
                Skills
            </div>

            <div class="form-grid pf-form-grid">
                <div class="form-field pf-form-wide">
                    <label class="form-field-lbl" for="pf-skills">{{ ProfilePolicy::labelOf('skills') }}</label>
                    <input id="pf-skills" name="skills" type="text"
                           value="{{ old('skills', implode(', ', $profile['skills'])) }}"
                           placeholder="Laravel, Vue, Code review">
                    <span class="pay-hint">
                        Shown on your profile and on team pages, so people know who to ask.
                    </span>
                </div>
            </div>

            @if ($profile['skills'] !== [])
                <ul class="pf-chips">
                    @foreach ($profile['skills'] as $skill)
                        <li class="pf-chip">{{ $skill }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="form-actions form-actions-padded">
            <button class="btn btn-primary" type="submit">Save changes</button>
            {{-- Cancel is a link back to the page, not a button that clears the
                 form. A reset button next to a save button is a mis-click that
                 throws away everything somebody just typed. --}}
            <a class="btn btn-outline" href="{{ route('profile.show') }}">Cancel</a>
        </div>
    </form>
@endsection
