<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in an internal chat channel between two staff stations —
 * Reception, Housekeeping, Maintenance, Security, or the Admin dashboard.
 * A channel is identified purely by its pair of roles ({@see channelKey()}),
 * so any two stations can message each other directly, not just each
 * department with Reception.
 */
class StaffMessage extends Model
{
    /** Every station that can take part in a staff chat channel. */
    public const ROLES = ['reception', 'housekeeping', 'maintenance', 'security', 'bar', 'kitchen', 'admin'];

    protected $fillable = [
        'channel_key', 'sender_role', 'sender_name', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * The channel two roles share — alphabetically sorted, so
     * `channelKey('maintenance', 'reception')` and
     * `channelKey('reception', 'maintenance')` are always the same row.
     */
    public static function channelKey(string $roleA, string $roleB): string
    {
        $pair = [$roleA, $roleB];
        sort($pair);

        return implode('_', $pair);
    }

    /**
     * The payload a chat screen renders, from [$viewerRole]'s point of
     * view — `is_mine` flips depending on which side of the channel is
     * looking at it.
     */
    public function toChatArray(string $viewerRole): array
    {
        return [
            'id' => $this->id,
            'sender_role' => $this->sender_role,
            'sender_name' => $this->sender_name,
            'body' => $this->body,
            'is_mine' => $this->sender_role === $viewerRole,
            'created_at' => $this->created_at?->toIso8601String(),
            'time_label' => optional($this->created_at)->format('g:i A'),
        ];
    }
}
