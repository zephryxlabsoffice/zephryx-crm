<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

/**
 * One thing addressed to one person.
 *
 * Written by App\Support\Notifier and read by App\Support\NotificationDirectory.
 * Nothing else constructs one: a notification with no event behind it is a
 * message somebody typed into somebody else's bell.
 */
class Notification extends Model
{
    /**
     * Which module the row came out of, and the sidebar icon that stands for it.
     *
     * A map rather than a stored column: the icon is a drawing decision the
     * navigation already makes, and a copy of it on every row would leave last
     * year's notifications pointing at an icon the application no longer has.
     *
     * A kind missing from here still renders — see `icon()` — because a
     * notification that reaches somebody without a picture is better than one
     * that 500s the page it appears on, and the bell is on every page.
     */
    public const ICONS = [
        'task' => 'tasks',
        'ticket' => 'tickets',
        'meeting' => 'meetings',
        'leave' => 'leave',
    ];

    protected $fillable = [
        'user_id', 'kind', 'title', 'body', 'link_route', 'link_params', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'link_params' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? 'announcements';
    }

    /**
     * A route that exists, or nothing.
     *
     * Decided 2026-08-28 and carried over from DemoNotifications: a
     * notification pointing at a page that has not been built — or at one that
     * has since been renamed — is worse than one with no link. It is a promise
     * the application cannot keep, and it fails at the moment somebody acts on
     * it, which is the one moment the notification was for.
     *
     * Checked at render rather than at write, because the route table is what
     * changes; the row does not.
     */
    public function link(): ?string
    {
        if ($this->link_route === null || ! Route::has($this->link_route)) {
            return null;
        }

        return route($this->link_route, $this->link_params ?? []);
    }

    /**
     * The row shape the feed and the bell both read.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'icon' => $this->icon(),
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'read_at' => $this->read_at?->toDateTimeString(),
            'when' => $this->created_at?->diffForHumans(short: true) ?? '',
        ];
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
