{{--
    A person's photo, or their initials when there is none (owner, 2026-11-07:
    the photo showed only on the Employees page and the top bar).

    `person` is an EmployeeDirectory row, or anything with a `name`; a
    `photo_url` on it is the photo. `class` adds to the avatar's classes, and
    `title` true shows the name on hover.
--}}
@php
    $avatarName = (string) ($person['name'] ?? '');
    $avatarPhoto = $person['photo_url'] ?? null;
    $avatarTitle = ($title ?? false) ? $avatarName : null;
@endphp
@if ($avatarPhoto)
    <img class="avatar {{ $class ?? '' }}" src="{{ $avatarPhoto }}" alt="{{ $avatarTitle ?? '' }}" @if ($avatarTitle) title="{{ $avatarTitle }}" @endif width="{{ $size ?? 34 }}" height="{{ $size ?? 34 }}" loading="lazy">
@else
    <span class="avatar {{ $class ?? '' }} {{ \App\Support\Avatar::tint($avatarName) }}" @if ($avatarTitle) title="{{ $avatarTitle }}" @else aria-hidden="true" @endif>{{ \App\Support\Avatar::initials($avatarName) }}</span>
@endif
