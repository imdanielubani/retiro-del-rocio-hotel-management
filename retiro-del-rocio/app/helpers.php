<?php

use App\Models\SiteContent;

if (! function_exists('cms')) {
    /**
     * Editable text content: stored value, else the schema default.
     */
    function cms(string $key, ?string $default = null): ?string
    {
        $default ??= config('cms.defaults')[$key] ?? null;

        return SiteContent::get($key, $default);
    }
}

if (! function_exists('cms_image')) {
    /**
     * Editable image: resolves the stored path (or schema default) to a URL.
     */
    function cms_image(string $key, ?string $default = null): ?string
    {
        $default ??= config('cms.defaults')[$key] ?? null;

        return SiteContent::imageUrl(SiteContent::get($key, $default));
    }
}

if (! function_exists('cms_array')) {
    /**
     * Editable repeater content stored as JSON, else the schema default array.
     *
     * @return array<int,array<string,string>>
     */
    function cms_array(string $key, ?array $default = null): array
    {
        $default ??= config('cms.defaults')[$key] ?? [];

        $raw = SiteContent::get($key);
        if (! $raw) {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }
}
