<?php

namespace App\Support;

/**
 * Display-side WebP helper. Given an image URL, returns its .webp sibling URL
 * when that file exists on the public path, otherwise the original URL.
 *
 * The .webp siblings themselves are produced by {@see ImageOptimizer} on upload
 * (and by the `images:webp` command for seeded images). This lets model
 * accessors serve WebP directly — no reliance on server-side Accept negotiation
 * (public/.htaccess), which does not run under nginx.
 *
 * Safe for local (/images, /storage) and external URLs and null; only rewrites
 * when a real .webp file is present on disk.
 */
class Webp
{
    public static function url(?string $url): ?string
    {
        if (! $url || ! preg_match('/\.(jpe?g|png)$/i', $url)) {
            return $url;
        }

        $webpUrl = preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $url);
        $path = parse_url($webpUrl, PHP_URL_PATH);

        if ($path && is_file(public_path(ltrim(urldecode($path), '/')))) {
            return $webpUrl;
        }

        return $url;
    }
}
