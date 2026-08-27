<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a project row into the things its pages need to draw it.
 */
class ProjectPresenter
{
    /** @var array<string, array{0: string, 1: string}> */
    protected const STATUSES = [
        'planning' => ['pill-blue', 'Planning'],
        'in_progress' => ['pill-green', 'In Progress'],
        'review' => ['pill-amber', 'In Review'],
        'on_hold' => ['pill-gray', 'On Hold'],
        'completed' => ['pill-indigo', 'Completed'],
        'cancelled' => ['pill-red', 'Cancelled'],
    ];

    /** @var array<string, string> */
    protected const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
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
     * @return array{tone: string, label: string}
     */
    public static function priority(string $priority): array
    {
        return [
            'tone' => 'priority-'.(isset(self::PRIORITIES[$priority]) ? $priority : 'low'),
            'label' => self::PRIORITIES[$priority] ?? ucfirst($priority),
        ];
    }

    /**
     * @return list<string>
     */
    public static function priorityOptions(): array
    {
        return array_keys(self::PRIORITIES);
    }

    public static function date(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    /**
     * How a deadline reads: the words, and whether it is a problem.
     *
     * A completed project past its date is not overdue — it landed late, which
     * is history, not an outstanding risk. The handover coloured every past
     * date red regardless.
     *
     * @return array{label: string, state: string}
     */
    public static function deadline(string $date, string $status = ''): array
    {
        $days = (int) Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false);

        if ($status === 'completed') {
            return ['label' => 'Delivered', 'state' => ''];
        }

        if ($days < 0) {
            $late = abs($days);

            return ['label' => $late === 1 ? '1 day overdue' : $late.' days overdue', 'state' => 'is-overdue'];
        }

        return match (true) {
            $days === 0 => ['label' => 'Due today', 'state' => 'is-overdue'],
            $days === 1 => ['label' => 'Due tomorrow', 'state' => 'is-soon'],
            $days <= 7 => ['label' => 'In '.$days.' days', 'state' => 'is-soon'],
            default => ['label' => 'In '.$days.' days', 'state' => ''],
        };
    }

    /**
     * Progress bars read as an achievement; below a fifth of the way through,
     * with the clock running, that is misleading.
     */
    public static function progressState(int $progress): string
    {
        return $progress > 0 && $progress < 20 ? 'is-early' : '';
    }

    /**
     * A stable icon tint for a project, derived from its reference.
     */
    public static function tint(string $reference): string
    {
        return Avatar::tint($reference);
    }
}
