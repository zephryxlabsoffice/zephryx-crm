{{-- One line of unassigned work. Also the template "Add Unassigned Task" copies. --}}
@php
    use App\Http\Controllers\EodController;
    use App\Support\TaskPresenter as P;
@endphp
<div class="eod-extra" data-eod-extra>
    <div class="form-field">
        <label class="form-field-lbl" for="eod-x-project-{{ $index }}">Project Name</label>
        <select id="eod-x-project-{{ $index }}" name="extra[{{ $index }}][project]" required>
            <option value="">Choose</option>
            <option value="{{ EodController::NO_PROJECT }}" @selected(($row['project'] ?? null) === EodController::NO_PROJECT)>No project</option>
            @foreach ($projectChoices as $project)
                <option value="{{ $project->id }}" @selected((string) ($row['project'] ?? '') === (string) $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        @error("extra.$index.project") <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label class="form-field-lbl" for="eod-x-title-{{ $index }}">Task Title</label>
        <input id="eod-x-title-{{ $index }}" type="text" name="extra[{{ $index }}][title]" required maxlength="160" value="{{ $row['title'] ?? '' }}">
        @error("extra.$index.title") <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label class="form-field-lbl" for="eod-x-status-{{ $index }}">Status</label>
        <select id="eod-x-status-{{ $index }}" name="extra[{{ $index }}][status]" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($row['status'] ?? 'completed') === $status)>{{ P::status($status)['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-field">
        <label class="form-field-lbl" for="eod-x-comment-{{ $index }}">Comments <span class="an-optional">(optional)</span></label>
        <input id="eod-x-comment-{{ $index }}" type="text" name="extra[{{ $index }}][comment]" maxlength="1000" value="{{ $row['comment'] ?? '' }}">
    </div>

    <button class="btn btn-outline btn-sm eod-remove" type="button" data-eod-remove>Remove</button>
</div>
