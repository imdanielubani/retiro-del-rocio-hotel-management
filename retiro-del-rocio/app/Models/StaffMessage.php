<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in an internal chat channel between Reception and a
 * department (Housekeeping, Maintenance, Security). Every channel is a
 * two-party conversation with the front desk — departments do not message
 * each other directly, so a department's whole channel is identified by
 * [department] alone.
 */
class StaffMessage extends Model
{
    public const RECEPTION = 'reception';

    /** The staff departments Reception can open a channel with. */
    public const DEPARTMENTS = ['housekeeping', 'maintenance', 'security'];

    protected $fillable = [
        'department', 'sender_role', 'sender_name', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function isFromReception(): bool
    {
        return $this->sender_role === self::RECEPTION;
    }

    /**
     * The payload a chat screen renders, from [$viewerRole]'s point of
     * view — `reception` on the front desk's screen, or the department's
     * own role once a department tablet gets its own chat screen.
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
