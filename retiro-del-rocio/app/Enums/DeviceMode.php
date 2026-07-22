<?php

namespace App\Enums;

/**
 * How a device is allocated:
 *  - Guest: bound to a specific room number (RoomUnit); serves that room's guest.
 *  - Staff: locked to a single role; staff of that role log into their own account.
 */
enum DeviceMode: string
{
    case Guest = 'guest';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'Guest',
            self::Staff => 'Staff',
        };
    }

    /** @return array{0: string, 1: string} [background, text] */
    public function badge(): array
    {
        return match ($this) {
            self::Guest => ['bg-[#e0f2fe]', 'text-[#0369a1]'],
            self::Staff => ['bg-[#ede9fe]', 'text-[#7c3aed]'],
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $m) => ['value' => $m->value, 'label' => $m->label()],
            self::cases(),
        );
    }
}
