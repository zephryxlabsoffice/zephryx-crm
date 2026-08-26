@php use App\Support\Avatar; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Birthdays</strong>
    </div>

    <div class="rail-list">
        @forelse ($birthdays as $person)
            <div class="person-row">
                <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">{{ Avatar::initials($person['name']) }}</span>
                <div class="person-body">
                    <strong>{{ $person['name'] }}</strong>
                    <span>{{ $person['date'] }}</span>
                </div>
                <span class="person-meta">{{ $person['countdown'] }}</span>
            </div>
        @empty
            {{-- Date of birth is a field on the Employees module, which does not
                 exist yet. Deliberately not invented — a wrong birthday is
                 worse than an absent one. --}}
            <p class="rail-empty">No birthdays recorded yet.</p>
        @endforelse
    </div>
</section>
