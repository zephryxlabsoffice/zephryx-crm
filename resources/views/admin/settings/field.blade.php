{{--
    One setting's input, by type.

    Kept as its own partial so the settings screen and the confirmation screen
    cannot disagree about what a value looks like — the confirmation re-renders
    the same field, disabled, beside the proposed one.
--}}
@switch($setting['type'])
    @case('select')
        <select id="{{ $id }}" name="value">
            @foreach ($setting['options'] as $value => $label)
                <option value="{{ $value }}" @selected((string) $setting['value'] === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        @break

    @case('toggle')
        {{-- A select rather than a checkbox: an unchecked checkbox posts
             nothing, which is indistinguishable from a field somebody never
             touched. For a value that governs whether the company's birthdays
             are announced, "absent" is not a safe reading of "off". --}}
        <select id="{{ $id }}" name="value">
            <option value="1" @selected((bool) $setting['value'])>On</option>
            <option value="0" @selected(! $setting['value'])>Off</option>
        </select>
        @break

    @case('days')
        {{-- The weekly off, which is a set rather than a value. Rendered as the
             days themselves; the write validates against 0–6 and stores a list. --}}
        <div class="ad-days">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $index => $day)
                <label class="ad-day">
                    <input type="checkbox" name="value[]" value="{{ $index }}"
                           @checked(in_array($index, (array) $setting['value'], true))>
                    <span>{{ $day }}</span>
                </label>
            @endforeach
        </div>
        @break

    @case('time')
        <input id="{{ $id }}" name="value" type="time" value="{{ $setting['value'] }}">
        @break

    @case('hours')
        <input id="{{ $id }}" name="value" type="number" step="0.5" min="0" max="24" value="{{ $setting['value'] }}">
        @break

    @case('number')
        <input id="{{ $id }}" name="value" type="number" min="0" value="{{ $setting['value'] }}">
        @break

    @case('email')
        <input id="{{ $id }}" name="value" type="email" value="{{ $setting['value'] }}">
        @break

    @default
        <input id="{{ $id }}" name="value" type="text" value="{{ $setting['value'] }}">
@endswitch
