{{--
    Escalating: Support, or whoever has the ticket, hands it on with a reason. The
    system suggests who it goes to (TicketWorkflow::suggestedEscalation) and the
    person escalating may change it. The reason becomes an internal note.
--}}
<section class="card" id="escalate">
    <div class="card-hd">
        <span class="card-title">Escalate</span>
    </div>

    <div class="card-body">
        <p class="dash-note">
            Cannot solve it? Hand it on. It goes to the person below, who is told straight away.
        </p>

        <form class="pay-form" method="POST" action="{{ route('tickets.escalate', ['ticket' => $ticket['id']]) }}">
            @csrf

            <div class="form-grid">
                <div class="form-field">
                    <label class="form-field-lbl" for="tkt-escalate-to">Escalate to</label>
                    <select id="tkt-escalate-to" name="escalate_to" required>
                        <option value="">Choose somebody</option>
                        @foreach ($escalationTargets as $target)
                            <option value="{{ $target['id'] }}" @selected((int) old('escalate_to', $suggestedEscalation) === $target['id'])>
                                {{ $target['name'] }}@if ($target['id'] === $suggestedEscalation) (suggested)@endif
                            </option>
                        @endforeach
                    </select>
                    @error('escalate_to')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="tkt-escalate-reason">Why</label>
                    <textarea id="tkt-escalate-reason" name="reason" rows="3" maxlength="1000" required
                              placeholder="What you tried, and what is still wrong.">{{ old('reason') }}</textarea>
                    @error('reason')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-outline" type="submit">Escalate ticket</button>
            </div>
        </form>
    </div>
</section>
