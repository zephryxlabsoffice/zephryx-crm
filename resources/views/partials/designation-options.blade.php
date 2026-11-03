{{-- Options for a designation select: $designationGroups, and the $selected id.
     data-department is what shell.js narrows the list by; a designation with no
     active department has none, so it stays on offer everywhere. --}}
@foreach ($designationGroups as $group)
    @if ($group['label'] !== null)
        <optgroup label="{{ $group['label'] }}">
    @endif
    @foreach ($group['items'] as $item)
        <option value="{{ $item->id }}" data-department="{{ $item->department?->is_active ? $item->department_id : '' }}" @selected((int) $selected === $item->id)>{{ $item->name }}</option>
    @endforeach
    @if ($group['label'] !== null)
        </optgroup>
    @endif
@endforeach
