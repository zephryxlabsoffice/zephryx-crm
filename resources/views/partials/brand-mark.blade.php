{{--
    The Z monogram. Two authored marks — black for light surfaces, white for
    dark — swapped by CSS on [data-theme] rather than by JavaScript, so the
    correct one is right on first paint.
--}}
<img class="brand-mark brand-mark-light" src="{{ asset('assets/brand/z-black.svg') }}" alt="{{ $alt ?? '' }}" width="72" height="72">
<img class="brand-mark brand-mark-dark" src="{{ asset('assets/brand/z-white.svg') }}" alt="{{ $alt ?? '' }}" width="72" height="72">
