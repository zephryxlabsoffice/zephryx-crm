<?php

namespace App\Support;

use App\Models\Announcement;
use Illuminate\Support\Carbon;

/**
 * What the compose form asks for each kind of announcement, and how the answers
 * become the text people read.
 *
 * The answers are written into `body` rather than kept in columns of their own,
 * so the board, the client board and search all carry on reading one field. The
 * only dates anything else reads are the closure dates, which keep their columns.
 *
 * @phpstan-type Form array{hint: string, title_label: string, title_placeholder: string, body_label: string, body_placeholder: string, body_required: bool, date_label: string|null, date_required: bool, time: bool, place_label: string|null, ends_after_date: bool}
 */
class AnnouncementForm
{
    /**
     * The form for every category a person may write in.
     *
     * @return array<string, Form>
     */
    public static function all(): array
    {
        $forms = [];

        foreach (array_keys(AnnouncementPresenter::authorableCategories()) as $category) {
            $forms[$category] = self::for((string) $category);
        }

        return $forms;
    }

    /**
     * A category added in the Admin Panel has no form of its own, so it gets the
     * plain subject and message.
     *
     * @return Form
     */
    public static function for(string $category): array
    {
        return (array) config("announcements.forms.{$category}", []) + (array) config('announcements.default_form');
    }

    /**
     * The text people read: the structured answers first, then the message.
     *
     * @param  array<string, mixed>  $data
     */
    public static function body(string $category, array $data): string
    {
        $form = self::for($category);
        $lines = [];

        if (($data['observed_from'] ?? null) !== null) {
            $lines[] = 'The office is closed '.self::closureText($data['observed_from'], $data['observed_to'] ?? null).'.';
        }

        if ($form['date_label'] !== null && ($data['event_date'] ?? null) !== null) {
            $lines[] = $form['date_label'].': '.self::longDate($data['event_date']);
        }

        if ($form['time'] && ($data['event_time'] ?? null) !== null) {
            $lines[] = 'Time: '.Carbon::parse($data['event_time'])->format('g:i A');
        }

        if ($form['place_label'] !== null && ($data['event_place'] ?? null) !== null) {
            $lines[] = $form['place_label'].': '.$data['event_place'];
        }

        return trim(implode("\n", $lines)."\n\n".trim((string) ($data['body'] ?? '')));
    }

    /**
     * When the notice comes off the board.
     *
     * A closure, an event and a training are stale once they have happened, so
     * those come off by themselves unless a date was given. It is never earlier
     * than the day the notice goes up, or a late notice would be born expired.
     *
     * @param  array<string, mixed>  $data
     */
    public static function endsOn(string $category, array $data): ?string
    {
        if (($data['ends_on'] ?? null) !== null) {
            return $data['ends_on'];
        }

        $lastDay = ($data['observed_from'] ?? null) !== null
            ? ($data['observed_to'] ?? $data['observed_from'])
            : (self::for($category)['ends_after_date'] ? ($data['event_date'] ?? null) : null);

        if ($lastDay === null) {
            return null;
        }

        return max(Carbon::parse($lastDay)->toDateString(), Carbon::parse($data['starts_on'])->toDateString());
    }

    /**
     * The answers to keep, so the post can be opened for editing later.
     *
     * @param  array<string, mixed>  $data
     * @return array{event_date: string|null, event_time: string|null, event_place: string|null, message: string}
     */
    public static function details(array $data): array
    {
        return [
            'event_date' => $data['event_date'] ?? null,
            'event_time' => $data['event_time'] ?? null,
            'event_place' => $data['event_place'] ?? null,
            'message' => trim((string) ($data['body'] ?? '')),
        ];
    }

    /**
     * What the form shows when a post is opened for editing.
     *
     * A post from before `details` existed has only its finished text, so that is
     * the message, less the closure sentence the form writes again from the dates.
     *
     * @return array<string, mixed>
     */
    public static function valuesFrom(Announcement $announcement): array
    {
        $from = $announcement->observed_from?->toDateString();
        $to = $announcement->observed_to?->toDateString();
        $details = $announcement->details;

        $message = $details['message'] ?? $announcement->body;

        if ($details === null && $from !== null) {
            $message = trim(str_replace('The office is closed '.self::closureText($from, $to).'.', '', $message));
        }

        return [
            'title' => $announcement->title,
            'category' => $announcement->category,
            'body' => $message,
            'event_date' => $details['event_date'] ?? null,
            'event_time' => $details['event_time'] ?? null,
            'event_place' => $details['event_place'] ?? null,
            'audience' => $announcement->audience,
            'audience_department_id' => $announcement->audience_department_id,
            'for_clients' => $announcement->for_clients,
            'starts_on' => $announcement->starts_on->toDateString(),
            'ends_on' => $announcement->ends_on?->toDateString(),
            'closes_office' => $from !== null ? '1' : '0',
            'observed_from' => $from,
            'observed_to' => $to,
            'pinned' => $announcement->pinned,
        ];
    }

    protected static function closureText(string $from, ?string $to): string
    {
        return $to === null || Carbon::parse($to)->isSameDay(Carbon::parse($from))
            ? 'on '.self::longDate($from)
            : 'from '.self::longDate($from).' to '.self::longDate($to);
    }

    protected static function longDate(string $date): string
    {
        return Carbon::parse($date)->format('l j F Y');
    }
}
