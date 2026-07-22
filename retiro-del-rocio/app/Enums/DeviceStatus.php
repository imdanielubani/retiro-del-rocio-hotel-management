<?php

namespace App\Enums;

/**
 * Lifecycle state of a physical device. Backed by string so it stores cleanly
 * and casts on the Device model. `label()`/`badge()` keep presentation in one
 * place so every table, card and API resource renders the status identically.
 */
enum DeviceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
    case Provisioning = 'provisioning';
    case Lost = 'lost';
    case Broken = 'broken';
    case Updating = 'updating';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Maintenance => 'Maintenance',
            self::Inactive => 'Inactive',
            self::Provisioning => 'Provisioning',
            self::Lost => 'Lost',
            self::Broken => 'Broken',
            self::Updating => 'Updating',
        };
    }

    /**
     * Tailwind classes for a status pill, matching the admin badge palette.
     *
     * @return array{0: string, 1: string} [background, text]
     */
    public function badge(): array
    {
        return match ($this) {
            self::Online => ['bg-[#dcfce7]', 'text-[#16a34a]'],
            self::Offline => ['bg-[#f3f4f6]', 'text-[#6b7280]'],
            self::Maintenance => ['bg-[#fef3c7]', 'text-[#b45309]'],
            self::Inactive => ['bg-[#f3f4f6]', 'text-[#9ca3af]'],
            self::Provisioning => ['bg-[#e0f2fe]', 'text-[#0369a1]'],
            self::Lost => ['bg-[#fee2e2]', 'text-[#b91c1c]'],
            self::Broken => ['bg-[#fee2e2]', 'text-[#dc2626]'],
            self::Updating => ['bg-[#ede9fe]', 'text-[#7c3aed]'],
        };
    }

    /** Solid hex for charts / progress bars. */
    public function hex(): string
    {
        return match ($this) {
            self::Online => '#16a34a',
            self::Provisioning => '#0369a1',
            self::Maintenance => '#b45309',
            self::Updating => '#7c3aed',
            self::Lost, self::Broken => '#dc2626',
            default => '#9ca3af',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Online => 'bg-[#16a34a]',
            self::Provisioning => 'bg-[#0369a1]',
            self::Maintenance => 'bg-[#b45309]',
            self::Updating => 'bg-[#7c3aed]',
            self::Lost, self::Broken => 'bg-[#dc2626]',
            default => 'bg-[#9ca3af]',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }

    /** Statuses an admin may set by hand (excludes system-driven ones). */
    public static function manuallyAssignable(): array
    {
        return [self::Online, self::Offline, self::Maintenance, self::Inactive, self::Lost, self::Broken];
    }
}
