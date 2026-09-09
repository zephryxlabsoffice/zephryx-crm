{{--
    Adding somebody to this team.

    On the page rather than behind a modal: it is one field and a button, and a
    dialog would need JavaScript to open — which puts a routine act behind a
    script that can fail to load.

    Only shown to somebody who may actually do it here — which is not the same
    as holding the permission. A Team Lead may manage their own team and no
    other (§2.6), and TeamController::canManageMembers is the one place that is
    decided.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Add a member</strong>
    </div>

    @if ($addable->isEmpty())
        <p class="att-rail-note">
            Everybody with an open record is already in this team.
        </p>
    @else
        <form method="POST" action="{{ route('teams.members.store', ['team' => $team['id']]) }}">
            @csrf

            <div class="form-field">
                <label class="form-field-lbl" for="team-add-member">Employee</label>
                {{-- Only people not already in the team, and only open records.
                     The same rule is on the validator, because a dropdown is
                     not where it is enforced. --}}
                <select id="team-add-member" name="employee_id" required>
                    @foreach ($addable as $employee)
                        <option value="{{ $employee->id }}">
                            {{ $employee->user?->name }} ({{ $employee->user?->user_id }})
                        </option>
                    @endforeach
                </select>
                @error('employee_id')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <button class="btn btn-primary" type="submit">Add to team</button>
        </form>
    @endif
</section>
