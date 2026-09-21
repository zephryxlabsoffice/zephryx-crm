<?php

namespace App\Support;

/**
 * Turns a client row into the things the list needs to draw it.
 *
 * Kept out of the view so the status vocabulary lives in one place. The values
 * themselves are App\Models\Client::STATUSES — the database enum, the
 * validation rule and the dropdown all read that one list, and this file only
 * decides how each of them is drawn.
 */
class ClientPresenter
{
    /**
     * Status → the pill tone and the words shown.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const STATUSES = [
        'active' => ['pill-green', 'Active'],
        'inactive' => ['pill-gray', 'Inactive'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    protected const PAYMENTS = [
        'paid' => ['pill-green', 'Paid'],
        'partial' => ['pill-amber', 'Partial'],
        'due' => ['pill-red', 'Due'],
    ];

    /**
     * @return array{tone: string, label: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label] = self::STATUSES[$status] ?? ['pill-gray', ucfirst(str_replace('_', ' ', $status))];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * The payment pill.
     *
     * Takes null and means it. Payment is the state of a client's invoices, and
     * until the Invoices module has a table there is no answer — so the pill
     * says so rather than defaulting to one of the three real states. "Due"
     * shown to somebody who is paid up is worse than an honest dash.
     *
     * @return array{tone: string, label: string}
     */
    public static function payment(?string $payment): array
    {
        if ($payment === null) {
            return ['tone' => 'pill-gray', 'label' => '—'];
        }

        [$tone, $label] = self::PAYMENTS[$payment] ?? ['pill-gray', ucfirst($payment)];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * Identity tint and initial. Shared with every other list that shows an
     * avatar — see App\Support\Avatar.
     */
    public static function tint(string $name): string
    {
        return Avatar::tint($name);
    }

    public static function initial(string $name): string
    {
        return Avatar::letter($name);
    }
}
