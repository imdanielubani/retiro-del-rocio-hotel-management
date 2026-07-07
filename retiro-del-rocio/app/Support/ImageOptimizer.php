<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Optimises an uploaded image in place: caps the width, re-encodes at web
 * quality, and respects EXIF orientation. Keeps the original format/filename
 * so callers don't need to change stored paths. Uses GD (always available on
 * the production image). Any failure is reported but never blocks the upload.
 */
class ImageOptimizer
{
    public static function optimize(
        string $path,
        string $disk = 'public',
        int $maxWidth = 1920,
        int $quality = 82,
    ): void {
        try {
            if (! function_exists('imagecreatetruecolor')) {
                return; // GD not available
            }

            $full = Storage::disk($disk)->path($path);
            if (! is_file($full)) {
                return;
            }

            $info = @getimagesize($full);
            if (! $info) {
                return;
            }
            [$w, $h] = $info;
            $mime = $info['mime'] ?? '';

            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($full),
                'image/png' => @imagecreatefrompng($full),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : null,
                default => null, // gif/svg/etc. — leave untouched
            };
            if (! $img) {
                return;
            }

            // Memory headroom for large photos.
            @ini_set('memory_limit', '512M');

            // Respect EXIF orientation on JPEGs so re-encoding never rotates them.
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($full);
                $o = $exif['Orientation'] ?? 1;
                if ($o == 3) {
                    $img = imagerotate($img, 180, 0);
                } elseif ($o == 6) {
                    $img = imagerotate($img, -90, 0);
                } elseif ($o == 8) {
                    $img = imagerotate($img, 90, 0);
                }
                $w = imagesx($img);
                $h = imagesy($img);
            }

            // Downscale if wider than the cap.
            $dst = $img;
            if ($w > $maxWidth) {
                $nw = $maxWidth;
                $nh = (int) round($h * $maxWidth / $w);
                $dst = imagecreatetruecolor($nw, $nh);
                if ($mime === 'image/png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            }

            // Re-encode in place (keep format).
            match ($mime) {
                'image/png' => imagepng($dst, $full, 6),
                'image/webp' => imagewebp($dst, $full, $quality),
                default => imagejpeg($dst, $full, $quality),
            };

            // Fast-delivery siblings: a full-size .webp plus responsive width
            // variants (each in the original format + .webp). These power the
            // <x-img> srcset and are served via Accept negotiation in
            // public/.htaccess — so uploads load as fast as the seeded images.
            self::writeVariants($dst, $full, $mime, $quality);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Responsive widths generated next to each image (only those < source). */
    protected static array $widths = [480, 768, 1080, 1440];

    protected static function writeVariants($dst, string $full, string $mime, int $quality): void
    {
        $webpOk = function_exists('imagewebp');
        $isWebpSrc = $mime === 'image/webp';
        $isPng = $mime === 'image/png';
        $finalW = imagesx($dst);
        $finalH = imagesy($dst);

        // Full-size .webp sibling (skip when the source already is webp).
        if (! $isWebpSrc && $webpOk) {
            $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $full);
            if ($webp && $webp !== $full) {
                @imagewebp($dst, $webp, $quality);
            }
        }

        foreach (self::$widths as $vw) {
            if ($vw >= $finalW) {
                continue; // never upscale
            }
            $vh = (int) round($finalH * $vw / $finalW);
            $v = imagecreatetruecolor($vw, $vh);
            imagealphablending($v, false);
            imagesavealpha($v, true);
            imagecopyresampled($v, $dst, 0, 0, 0, 0, $vw, $vh, $finalW, $finalH);

            if ($isWebpSrc) {
                @imagewebp($v, preg_replace('/\.webp$/i', "-{$vw}.webp", $full), $quality);
            } else {
                $sameExt = preg_replace('/(\.(?:jpe?g|png))$/i', "-{$vw}\$1", $full);
                $isPng ? @imagepng($v, $sameExt, 6) : @imagejpeg($v, $sameExt, $quality);
                if ($webpOk) {
                    @imagewebp($v, preg_replace('/\.(jpe?g|png)$/i', "-{$vw}.webp", $full), $quality);
                }
            }
        }
    }
}
