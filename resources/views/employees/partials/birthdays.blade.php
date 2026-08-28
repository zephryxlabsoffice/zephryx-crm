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
            {{-- Date of birth landed with Announcements (2026-08-28); this list
                 is now real. It shows a day and a month and never a year — see
                 App\Support\Milestones. Empty means nobody has one in the next
                 fortnight, or they have opted out of being announced. --}}
            <p class="rail-empty">No birthdays in the next fortnight.</p>
        @endforelse
    </div>
</section>
