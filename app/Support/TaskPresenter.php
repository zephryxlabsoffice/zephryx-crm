<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a task row into the things its pages need to draw it.
 */
class TaskPresenter
{
    /** @var array<string, array{0: string, 1: string}> */
    protected const STATUSES = [
        'pending' => ['pill-gray', 'Pending'],
        'in_progress' => ['pill-green', 'In Progress'],
        'review' => ['pill-amber', 'In Review'],
        'completed' => ['pill-indigo', 'Completed'],
        'blocked' => ['pill-red', 'Blocked'],
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
     * Priority shares its vocabulary with Projects — the same words must mean
     * the same thing in both places.
     *
     * @return array{tone: string, label: string}
     */
    public static function priority(string $priority): array
    {
        return ProjectPresenter::priority($priority);
    }

    /**
     * @return list<string>
     */
    public static function priorityOptions(): array
    {
        return ProjectPresenter::priorityOptions();
    }

    public static function date(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    /**
     * How long is left, and whether it is a problem.
     *
     * A completed task past its date is not overdue — the same rule Projects
     * uses, so the two modules do not contradict each other.
     *
     * @return array{label: string, state: string}
     */
    public static function due(string $date, string $status = ''): array
    {
        if ($status === 'completed') {
            return ['label' => 'Completed', 'state' => 'is-done'];
        }

        $days = (int) Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false);

        if ($days < 0) {
            $late = abs($days);

            return ['label' => $late === 1 ? '1 day overdue' : $late.' days overdue', 'state' => 'is-overdue'];
        }

        return match (true) {
            $days === 0 => ['label' => 'Due today', 'state' => 'is-overdue'],
            $days === 1 => ['label' => '1 day left', 'state' => 'is-soon'],
            $days <= 7 => ['label' => $days.' days left', 'state' => 'is-soon'],
            default => ['label' => $days.' days left', 'state' => ''],
        };
    }

    /**
     * The icon and class for an attachment, from its file name.
     */
    public static function fileType(string $filename): array
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => ['class' => 'ft-pdf', 'label' => 'PDF'],
            'doc', 'docx', 'rtf', 'txt' => ['class' => 'ft-doc', 'label' => 'DOC'],
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => ['class' => 'ft-img', 'label' => 'IMG'],
            'fig', 'sketch', 'xd', 'psd', 'ai' => ['class' => 'ft-design', 'label' => mb_strtoupper($extension)],
            'xls', 'xlsx', 'csv' => ['class' => 'ft-sheet', 'label' => 'XLS'],
            default => ['class' => '', 'label' => mb_strtoupper(mb_substr($extension, 0, 3)) ?: 'FILE'],
        };
    }

    public static function tint(string $reference): string
    {
        return Avatar::tint($reference);
    }

    /**
     * A human label for an attachment's kind, from its file name.
     *
     * Only ever one of DocumentStore::ALLOWED — the upload is validated
     * against that same list — so this is exhaustive rather than a fallback
     * for a type nothing here accepts.
     */
    public static function fileKind(string $filename): string
    {
        return match (mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'PDF document',
            'png', 'jpg', 'jpeg' => 'Image',
            default => 'File',
        };
    }

    /**
     * Bytes, as somebody reads them — one decimal place above a megabyte,
     * whole kilobytes below it.
     */
    public static function fileSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
