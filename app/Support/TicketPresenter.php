<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a ticket row into the things its pages need to draw it.
 */
class TicketPresenter
{
    /** @var array<string, array{0: string, 1: string}> */
    protected const STATUSES = [
        'unassigned' => ['pill-gray', 'Unassigned'],
        'open' => ['pill-blue', 'Open'],
        'in_progress' => ['pill-amber', 'In Progress'],
        'escalated' => ['pill-red', 'Escalated'],
        'resolved' => ['pill-green', 'Resolved'],
        'closed' => ['pill-gray', 'Closed'],
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
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * Priority shares its vocabulary with Projects and Tasks — the same word
     * must mean the same thing everywhere it appears.
     *
     * @return array{tone: string, label: string}
     */
    public static function priority(?string $priority): array
    {
        if ($priority === null || $priority === '') {
            return ['tone' => 'priority-low', 'label' => 'Not set'];
        }

        return ProjectPresenter::priority($priority);
    }

    /**
     * @return list<string>
     */
    public static function priorityOptions(): array
    {
        return ProjectPresenter::priorityOptions();
    }

    /**
     * @return list<string>
     */
    public static function typeOptions(): array
    {
        return ['internal', 'client'];
    }

    public static function typeLabel(string $type): string
    {
        return $type === 'client' ? 'Client' : 'Internal';
    }

    public static function date(Carbon|string $when): string
    {
        return Carbon::parse($when)->format('d M Y');
    }

    public static function time(Carbon|string $when): string
    {
        return Carbon::parse($when)->format('g:i A');
    }

    public static function ago(Carbon|string $when): string
    {
        return Carbon::parse($when)->diffForHumans();
    }

    /**
     * A ticket nobody has triaged is missing the fields triage sets. Rendering
     * those as a blank cell hides the fact that something still needs doing,
     * so they read as "Not set" instead.
     */
    public static function orNotSet(?string $value): string
    {
        return ($value === null || $value === '') ? 'Not set' : $value;
    }

    /**
     * Whether a comment is visible to a client.
     *
     * The client realm filters server-side before rendering; this is the
     * second line of defence, deciding how the note is drawn for staff.
     */
    public static function isInternal(array $comment): bool
    {
        // Anything not explicitly marked public is treated as internal. A
        // missing or unrecognised value must fail towards secrecy, never
        // towards a client reading something they should not.
        return ($comment['visibility'] ?? 'internal') !== 'public';
    }
}
