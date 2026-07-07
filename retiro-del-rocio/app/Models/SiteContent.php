<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteContent extends Model
{
    protected $fillable = ['key', 'value'];

    /** Process-level cache of key => value so the public site hits the DB once per request. */
    protected static ?array $cache = null;

    /** @return array<string,string|null> */
    public static function map(): array
    {
        return static::$cache ??= static::query()->pluck('value', 'key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::map()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    /**
     * Resolve a stored image path to a public URL (mirrors Room::imageUrl).
     * - null            → null
     * - "images/x.jpg"  → asset() (bundled files in /public/images)
     * - http(s)://…     → as-is
     * - "cms/x.jpg"     → Storage public disk (admin uploads)
     */
    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'images/')) {
            return \App\Support\Webp::url(str_replace(' ', '%20', asset($path)));
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return \App\Support\Webp::url(Storage::disk('public')->url($path));
    }
}
