<?php

namespace App\Support;

/**
 * Turns a client row into the things the list needs to draw it.
 *
 * Kept out of the view so the status vocabulary lives in one place: when the
 * Clients migration lands with a real `status` enum, only this file changes.
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
        'pending' => ['pill-amber', 'Pending'],
        'review' => ['pill-blue', 'In Review'],
        'on_hold' => ['pill-amber', 'On Hold'],
        'completed' => ['pill-gray', 'Completed'],
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
     * @return array{tone: string, label: string}
     */
    public static function payment(string $payment): array
    {
        [$tone, $label] = self::PAYMENTS[$payment] ?? ['pill-gray', ucfirst($payment)];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * A stable identity tint for a client, derived from the name.
     *
     * Derived rather than assigned so the same client always gets the same
     * colour and the eighth one does not fall through to a default — the
     * handover hand-numbered av-1..av-7 against seven sample rows.
     */
    public static function tint(string $name): string
    {
        return 'tint-'.((crc32(mb_strtolower(trim($name))) % 7) + 1);
    }

    public static function initial(string $name): string
    {
        return mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: '?';
    }
}
