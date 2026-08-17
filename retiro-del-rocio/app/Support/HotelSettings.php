<?php

namespace App\Support;

use App\Http\Middleware\CheckSiteMaintenance;
use App\Models\SiteContent;
use Illuminate\Support\Carbon;

/**
 * The hotel's operational profile and front-desk policy — everything on
 * Settings → Hotel Info.
 *
 * Values live in `site_contents` under the `hotel.*` namespace, so they are
 * edited in one place and read everywhere (the in-room tablets, booking
 * details, emails). Anything unset falls back to `config/hotel.php`, which is
 * still env-overridable.
 *
 * Note this is deliberately separate from the `contact.*` keys the public
 * website's CMS owns: marketing copy and front-desk policy change for different
 * reasons and by different people.
 */
class HotelSettings
{
    public const CHECK_IN_KEY = 'hotel.check_in_time';

    public const CHECK_OUT_KEY = 'hotel.check_out_time';

    public const MAINTENANCE_KEY = 'site.maintenance_mode';

    public const MAINTENANCE_MESSAGE_KEY = 'site.maintenance_message';

    public const DEFAULT_MAINTENANCE_MESSAGE = "We're currently performing scheduled maintenance. Please check back shortly.";

    /** Editable profile fields: key => config fallback. */
    public const PROFILE = [
        'hotel.name' => 'name',
        'hotel.tagline' => 'tagline',
        'hotel.address' => 'address',
        'hotel.city' => 'city',
        'hotel.country' => 'country',
        'hotel.phone' => 'phone',
        'hotel.email' => 'email',
        'hotel.description' => 'description',
    ];

    /** A profile field, e.g. `get('hotel.name')`. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $fallback = $default ?? (
            isset(self::PROFILE[$key]) ? config('hotel.'.self::PROFILE[$key]) : null
        );

        return SiteContent::get($key, $fallback !== null ? (string) $fallback : null);
    }

    public static function put(string $key, ?string $value): void
    {
        SiteContent::put($key, $value === null ? null : trim($value));
    }

    /** Arrival time, as `HH:MM` (24-hour). */
    public static function checkInTime(): string
    {
        return self::normalise(
            SiteContent::get(self::CHECK_IN_KEY, (string) config('hotel.check_in_time')),
            '15:00'
        );
    }

    /** Departure time, as `HH:MM` (24-hour). */
    public static function checkOutTime(): string
    {
        return self::normalise(
            SiteContent::get(self::CHECK_OUT_KEY, (string) config('hotel.check_out_time')),
            '12:00'
        );
    }

    public static function setCheckInTime(string $time): void
    {
        SiteContent::put(self::CHECK_IN_KEY, self::normalise($time, '15:00'));
    }

    public static function setCheckOutTime(string $time): void
    {
        SiteContent::put(self::CHECK_OUT_KEY, self::normalise($time, '11:00'));
    }

    /** e.g. "3:00 PM" — for display in the dashboard and emails. */
    public static function checkInLabel(): string
    {
        return self::label(self::checkInTime());
    }

    public static function checkOutLabel(): string
    {
        return self::label(self::checkOutTime());
    }

    public static function label(string $time): string
    {
        [$hour, $minute] = explode(':', self::normalise($time, '00:00'));

        return Carbon::createFromTime((int) $hour, (int) $minute)->format('g:i A');
    }

    /** A booking date at the given policy time. */
    public static function at(?Carbon $date, string $time): ?Carbon
    {
        if (! $date) {
            return null;
        }

        [$hour, $minute] = explode(':', self::normalise($time, '00:00'));

        return $date->copy()->setTime((int) $hour, (int) $minute);
    }

    /** A booking's arrival moment (its date at the hotel's check-in time). */
    public static function arrivalFor(?Carbon $checkInDate): ?Carbon
    {
        return self::at($checkInDate, self::checkInTime());
    }

    /** A booking's departure moment (its date at the hotel's check-out time). */
    public static function departureFor(?Carbon $checkOutDate): ?Carbon
    {
        return self::at($checkOutDate, self::checkOutTime());
    }

    /**
     * Whether the public website is currently showing the maintenance page.
     * Deliberately independent of Laravel's own `php artisan down` — that
     * would take the admin panel down along with the public site, and the
     * whole point of a dashboard toggle is being able to turn it back off.
     * See {@see CheckSiteMaintenance}.
     */
    public static function maintenanceMode(): bool
    {
        return SiteContent::get(self::MAINTENANCE_KEY, '0') === '1';
    }

    public static function setMaintenanceMode(bool $enabled): void
    {
        SiteContent::put(self::MAINTENANCE_KEY, $enabled ? '1' : '0');
    }

    /** The message shown to visitors on the maintenance page. */
    public static function maintenanceMessage(): string
    {
        return SiteContent::get(self::MAINTENANCE_MESSAGE_KEY, self::DEFAULT_MAINTENANCE_MESSAGE)
            ?: self::DEFAULT_MAINTENANCE_MESSAGE;
    }

    public static function setMaintenanceMessage(?string $message): void
    {
        $message = trim((string) $message);
        SiteContent::put(self::MAINTENANCE_MESSAGE_KEY, $message !== '' ? $message : null);
    }

    /** Accept `15:00`, `15:00:00` or `9:5`; fall back when it is unusable. */
    private static function normalise(?string $time, string $fallback): string
    {
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{1,2})/', trim($time), $m)) {
            return $fallback;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour > 23 || $minute > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
