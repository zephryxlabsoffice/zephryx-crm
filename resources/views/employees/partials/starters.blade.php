@php use App\Support\Avatar; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>New Employees</strong>
        <a class="card-link" href="{{ route('employees.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($starters as $person)
            <div class="person-row">
                <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">{{ Avatar::initials($person['name']) }}</span>
                <div class="person-body">
                    <strong>{{ $person['name'] }}</strong>
                    <span>{{ $person['designation'] }}</span>
                </div>
                <span class="person-meta">{{ $person['when'] }}</span>
            </div>
        @empty
            <p class="rail-empty">Nobody has joined recently.</p>
        @endforelse
    </div>
</section>
