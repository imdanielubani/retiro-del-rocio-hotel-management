<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in an internal chat channel between two individual staff
 * members — any tablet role (Reception, Housekeeping, Maintenance,
 * Security, Bar, Kitchen) or an admin-portal user. A channel is identified
 * by the pair of user IDs ({@see channelKey()}), so two specific people
 * always share exactly one thread, whether or not they hold the same role
 * as anyone else — this is what lets "Bar 1" and "Bar 2" be messaged
 * separately even though both hold the `bar` role.
 */
class StaffMessage extends Model
{
    /** Every tablet role that can take part in a staff chat channel. */
    public const ROLES = ['reception', 'housekeeping', 'maintenance', 'security', 'bar', 'kitchen'];

    /** Roles that grant admin-portal ("Manager") chat access — {@see User::isAdmin()}. */
    public const ADMIN_ROLES = ['super-admin', 'admin', 'manager', 'it-administrator'];

    protected $fillable = [
        'channel_key', 'sender_id', 'recipient_id', 'sender_role', 'sender_name', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * The channel two users share — sorted numerically, so
     * `channelKey(12, 47)` and `channelKey(47, 12)` are always the same row.
     */
    public static function channelKey(int $userIdA, int $userIdB): string
    {
        $pair = [$userIdA, $userIdB];
        sort($pair);

        return implode('_', $pair);
    }

    /**
     * The payload a chat screen renders, from [$viewerId]'s point of
     * view — `is_mine` flips depending on which side of the channel is
     * looking at it.
     */
    public function toChatArray(int $viewerId): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'sender_role' => $this->sender_role,
            'sender_name' => $this->sender_name,
            'body' => $this->body,
            'is_mine' => $this->sender_id === $viewerId,
            'created_at' => $this->created_at?->toIso8601String(),
            'time_label' => optional($this->created_at)->format('g:i A'),
        ];
    }
}
