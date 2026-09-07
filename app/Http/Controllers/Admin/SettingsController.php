<?php

namespace App\Http\Controllers\Admin;

use App\Support\Admin\Retroactive;
use App\Support\Admin\SettingsCatalogue;
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
 * WHAT THE BACKEND OWES
 *
 * 1. WRITE TO `company_settings` (§8), not to config files. A deployed
 *    application cannot edit its own source, and a value that lives in a file
 *    is a value that a deploy silently reverts.
 *
 * 2. AUDIT EVERY CHANGE with actor, before, after and the computed effect (§6).
 *
 * 3. RE-COMPUTE THE PREVIEW AT SAVE TIME and refuse if it has moved. The
 *    figures shown on the confirmation are a snapshot; between the two steps
 *    somebody may have checked in. Confirming against a stale preview would
 *    mean agreeing to a number nobody ever saw.
 *
 * 4. VALIDATE AGAINST THE CATALOGUE. A key not in SettingsCatalogue::groups()
 *    is not a setting, and a posted one must be dropped rather than written —
 *    the form is not the guard.
 */
class SettingsController extends Controller
{
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
            'effect' => $setting['retroactive']
                ? Retroactive::preview($setting['key'], $proposed)
                : null,
        ]);
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
}
