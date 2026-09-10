<?php

namespace App\Http\Controllers\Admin;

use App\Support\Admin\CompanySettings;
use App\Support\Admin\Retroactive;
use App\Support\Admin\SettingsCatalogue;
use App\Support\Audit\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Company configuration.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * SAVING IS TWO STEPS, AND THE FIRST ONE IS A READ
 *
 * The reason is the finding at the head of App\Support\Admin\SettingsCatalogue:
 * Attendance and Leave do not store their judgements. Half days, days that
 * stopped counting, weekly offs, leave balances — all derived on read, from
 * these values, every time somebody opens a page.
 *
 * So changing `half_day_hours` is not a change that takes effect tomorrow. It
 * reclassifies months of days that have already been worked and reported on,
 * with no notification and no trace in the records themselves.
 *
 * `preview()` computes exactly what would move and names it — "47 days across
 * 11 people would be re-judged", with examples — before anything is written.
 * Only the second step saves, and the audit entry records the EFFECT alongside
 * the value, because "half_day_hours: 4 → 6" is a true log line and a useless
 * one.
 *
 * The two-step shape is borrowed from marking payroll paid, for the same
 * reason: an act that is hard to notice and awkward to undo gets a step that
 * names who it lands on.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * HOW THE FOUR OBLIGATIONS ARE MET
 *
 * 1. IT WRITES TO `company_settings` (§8), not to config files. A deployed
 *    application cannot edit its own source, and a value that lives in a file
 *    is a value the next deploy silently reverts. Config stays the DEFAULT and
 *    the table is the decision — see App\Support\Admin\CompanySettings.
 *
 * 2. EVERY CHANGE IS AUDITED with actor, before, after and the computed effect
 *    (§6). The effect is the point: "half_day_hours: 4 → 6" is a true log line
 *    and a useless one.
 *
 * 3. THE PREVIEW IS RE-COMPUTED AT SAVE TIME and the save is refused if it has
 *    moved. The figures on the confirmation are a snapshot; between the two
 *    steps somebody may have checked in, and confirming against a stale preview
 *    means agreeing to a number nobody ever saw.
 *
 * 4. THE CATALOGUE IS THE VALIDATOR. A key not in SettingsCatalogue::groups()
 *    is not a setting — the form is not the guard, and `config([$key => …])`
 *    with an unchecked key would let a posted field rewrite anything in the
 *    configuration.
 */
class SettingsController extends Controller
{
    public function __construct(protected AuditLog $audit)
    {
    }

    public function index(Request $request): Response
    {
        return response()->view('admin.settings.index', [
            'activeNav' => 'settings',
            'groups' => SettingsCatalogue::groups(),
        ]);
    }

    /**
     * POST /admin/settings/preview — what the proposed change would do.
     *
     * A POST that renders. The proposed values should not end up in a URL that
     * gets bookmarked, shared or written to an access log, which is the same
     * reason `salary.pay.confirm` is a POST.
     */
    public function preview(Request $request): Response
    {
        $validated = $request->validate([
            // Validated against the catalogue: a key that is not a setting is
            // not a setting, whatever the form posted.
            'key' => ['required', 'string', 'max:120'],
            // Not `required`: the weekly off is a set, and clearing it to none
            // is a legitimate — and very consequential — change. `required`
            // would silently refuse the one edit most worth reviewing.
            'value' => ['nullable'],
            'value.*' => ['string'],
        ]);

        $setting = SettingsCatalogue::find($validated['key']);

        abort_if($setting === null, 404);

        $proposed = $this->normalise($setting, $validated['value'] ?? null);

        return response()->view('admin.settings.preview', [
            'activeNav' => 'settings',
            'setting' => $setting,
            'proposed' => $proposed,
            'unchanged' => $this->same($proposed, $setting['value']),
            /*
             * Computed live against the real history rather than estimated.
             * See Retroactive for why this is safe to run here and how the
             * configuration is restored afterwards.
             */
            'effect' => $effect = $setting['retroactive']
                ? Retroactive::preview($setting['key'], $proposed)
                : null,
            /*
             * What the person is about to agree to, in one string. Posted back
             * with the save and compared against a freshly computed one — see
             * `update()`. Not a security token: it is a "has the world moved"
             * check, and the CSRF token next to it is the security one.
             */
            'fingerprint' => $this->fingerprint($effect),
        ]);
    }

    /**
     * POST /admin/settings — the second step, and the one that writes.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['nullable'],
            'value.*' => ['string'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
        ]);

        $setting = SettingsCatalogue::find($validated['key']);

        // A key outside the catalogue is not a setting, whatever was posted.
        abort_if($setting === null, 404);

        $proposed = $this->normalise($setting, $validated['value'] ?? null);

        if ($this->same($proposed, $setting['value'])) {
            return redirect()
                ->route('admin.settings')
                ->with('status', 'That is the value it already had. Nothing changed.')
                ->with('status_tone', 'info');
        }

        $effect = $setting['retroactive']
            ? Retroactive::preview($setting['key'], $proposed)
            : null;

        /*
         * ─────────────────────────────────────────────────────────────────────
         * THE PREVIEW IS RE-COMPUTED, AND A CHANGED ONE STOPS THE SAVE
         *
         * The figures the person read were computed on the previous request.
         * Somebody checking in, a leave request being approved, or simply the
         * clock passing midnight can all move them — and the whole argument for
         * the two-step is that the second step is agreement to a specific
         * number.
         *
         * So a moved preview sends them back to look at the new one rather than
         * writing against the old. Annoying exactly once, and the alternative
         * is a confirmation that means nothing.
         * ─────────────────────────────────────────────────────────────────────
         */
        $fingerprint = $this->fingerprint($effect);

        if (($validated['fingerprint'] ?? null) !== null && $validated['fingerprint'] !== $fingerprint) {
            return redirect()
                ->route('admin.settings')
                ->with('status', 'The records this would change have moved since you looked. '
                    .'Nothing was saved — start again so you are agreeing to the current figures.')
                ->with('status_tone', 'warning');
        }

        $before = $setting['value'];

        CompanySettings::put($setting['key'], $proposed, $request->user());

        $this->audit->record(
            action: AuditLog::SETTING_CHANGED,
            actor: $request->user(),
            entityType: 'setting',
            entityId: $setting['key'],
            before: $this->readable($before),
            /*
             * The effect, not just the value. §6's `after` is meant to describe
             * what happened, and for these keys what happened is that records
             * nobody edited now read differently — which the records themselves
             * do not show.
             */
            after: $this->readable($proposed)
                .($effect !== null && $effect['affected'] > 0 ? ' — '.$effect['summary'] : ''),
            request: $request,
        );

        return redirect()
            ->route('admin.settings')
            ->with('status', $setting['label'].' saved.'
                .($effect !== null && $effect['affected'] > 0 ? ' '.ucfirst($effect['summary']).'.' : ''))
            ->with('status_tone', 'success');
    }

    /**
     * Bring a posted value into the shape the setting actually holds.
     *
     * Everything arrives from a form as a string or an array of strings.
     * `week_off` is a list of integers and the policy compares with `in_array`
     * using strict types, so leaving them as strings would make every day
     * silently fail to match — the preview would report no change on the one
     * setting whose change is most dramatic.
     *
     * @param  array<string, mixed>  $setting
     */
    protected function normalise(array $setting, mixed $value): mixed
    {
        if ($setting['type'] === 'days') {
            return array_values(array_map('intval', (array) ($value ?? [])));
        }

        return $value;
    }

    /**
     * Whether the proposed value is the one already in force.
     *
     * Compared by shape rather than by casting to string: a set has no useful
     * string form, and `(string) $array` is a warning rather than a comparison.
     */
    protected function same(mixed $proposed, mixed $current): bool
    {
        if (is_array($proposed) || is_array($current)) {
            $a = array_map('strval', (array) $proposed);
            $b = array_map('strval', (array) $current);

            sort($a);
            sort($b);

            return $a === $b;
        }

        return (string) $proposed === (string) $current;
    }

    /**
     * One string standing for the effect the person was shown.
     *
     * The summary and the count, hashed. Deliberately NOT the examples: those
     * are a handful of illustrative rows and the order they come back in is not
     * guaranteed, so including them would refuse saves that agree perfectly
     * with what was on the screen.
     *
     * @param  array<string, mixed>|null  $effect
     */
    protected function fingerprint(?array $effect): string
    {
        if ($effect === null) {
            // A setting with no retroactive effect has nothing to go stale.
            return '';
        }

        return hash('sha256', $effect['affected'].'|'.$effect['people'].'|'.$effect['summary']);
    }

    /**
     * A value in words, for the audit entry.
     *
     * "0, 6" is a correct and unreadable description of the weekly off, and the
     * log is read by somebody who was not on the screen that produced it.
     */
    protected function readable(mixed $value): string
    {
        if (is_array($value)) {
            return $value === [] ? 'none' : implode(', ', array_map('strval', $value));
        }

        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }

        return (string) $value;
    }
}
