{{-- Options for a designation select: $designationGroups, and the $selected id. --}}
@foreach ($designationGroups as $group)
    @if ($group['label'] !== null)
        <optgroup label="{{ $group['label'] }}">
    @endif
    @foreach ($group['items'] as $item)
        <option value="{{ $item->id }}" @selected((int) $selected === $item->id)>{{ $item->name }}</option>
    @endforeach
    @if ($group['label'] !== null)
        </optgroup>
    @endif
@endforeach
