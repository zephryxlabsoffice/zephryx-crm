{{--
    A setting's value, written the way a person says it.

    The weekly off is stored as day numbers because that is what
    Carbon::dayOfWeek returns, and "0, 6" is a correct and unreadable way to
    show it on a confirmation screen — the one place somebody has to be certain
    what they are agreeing to. Days get their names here.
--}}
@php
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $written = match (true) {
        ($setting['type'] ?? '') === 'days' => ((array) $value) === []
            ? 'None — the office is open every day'
            : implode(', ', array_map(fn ($d) => $dayNames[(int) $d] ?? $d, (array) $value)),

        ($setting['type'] ?? '') === 'toggle' => $value ? 'On' : 'Off',

        is_array($value) => implode(', ', $value),

        (string) $value === '' => '—',

        default => (string) $value,
    };
@endphp
{{ $written }}
