@php use App\Support\Avatar; @endphp

{{--
    Who the record belongs to.

    Deliberately thin: name, staff id, department, designation. An attendance
    record is not a place to restate somebody's employment file, and a rail that
    grows one field at a time is how a page about one day ends up being a profile
    page with a date on it.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>{{ $own ? 'You' : 'Employee' }}</strong>
    </div>

    <div class="person-row">
        <span class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($record['employee_record']['name']) }}</span>
        <span class="person-body">
            <strong>{{ $record['employee_record']['name'] }}</strong>
            <span>{{ $record['employee_record']['designation'] }}</span>
        </span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Staff ID</span>
        <span class="stat-value">{{ $record['employee'] }}</span>
    </div>

    <div class="stat-row">
        <span class="stat-label">Department</span>
        <span class="stat-value">{{ $record['employee_record']['department'] }}</span>
    </div>

    @unless ($own)
        <a class="btn btn-outline btn-sm att-rail-btn" href="{{ route('attendance.index', ['date' => $record['date'], 'q' => $record['employee']]) }}">
            That day’s roll
        </a>
    @endunless
</section>
