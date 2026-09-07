{{--
    The role preview switcher.

    Renders only in local + debug: App\Support\Demo\DemoRoles returns an empty
    registry anywhere else, so this whole block disappears in a deployed
    application along with every other Demo class.

    ─────────────────────────────────────────────────────────────────────────────
    WHY IT HAS TO EXIST

    PermissiveGate allows everything, which makes a permission-composed page
    impossible to review: against a gate that says yes, the dashboard renders
    every widget at once, which is the one layout no real account will ever see.
    `?as=hr` narrows it to one role's set.

    It is links, not a form and not a script — a GET that changes nothing, so it
    is bookmarkable, back-buttonable, and needs no CSRF token because it writes
    nothing.

    The preview is INTERSECTED with the real gate, never substituted for it
    (DashboardController::gate), so it can only ever hide widgets, never reveal
    them.
    ─────────────────────────────────────────────────────────────────────────────
--}}
@if ($previewRoles !== [])
    <section class="dash-preview" aria-label="Role preview">
        <div class="dash-preview-hd">
            <span class="dash-preview-tag">Development preview</span>
            <p>
                @if ($preview)
                    Showing the dashboard as a <strong>{{ $preview['label'] }}</strong> sees it —
                    narrowed from what your own account holds, never widened.
                    {{ $preview['note'] }}
                @else
                    {{-- Was "the gate is a placeholder that allows everything",
                         which stopped being true when the RBAC engine landed
                         (2026-09-07). This now shows what the signed-in account
                         actually holds; the roles below only ever subtract. --}}
                    Showing what <strong>your own account</strong> holds. Picking a role
                    narrows the page to that role's composition — the preview is
                    intersected with your permissions and can only ever hide widgets.
                @endif
            </p>
        </div>

        <div class="dash-preview-roles">
            <a class="chip-btn {{ $preview ? '' : 'chip-btn-accent' }}" href="{{ route('dashboard') }}">Everything</a>

            @foreach ($previewRoles as $key => $role)
                <a class="chip-btn {{ ($preview['key'] ?? null) === $key ? 'chip-btn-accent' : '' }}"
                   href="{{ route('dashboard', ['as' => $key]) }}">{{ $role['label'] }}</a>
            @endforeach
        </div>
    </section>
@endif
