<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds fast-loading image assets for every JPG/PNG under public/images and
 * the public storage disk:
 *   - a .webp sibling of each image (served automatically via public/.htaccess)
 *   - resized width variants (name-480.jpg, name-768.jpg, …) + their .webp,
 *     used by the <x-img> component's srcset so phones download a small image.
 *
 *   php artisan images:webp            # only create missing/stale files
 *   php artisan images:webp --force    # regenerate everything
 */
class GenerateWebp extends Command
{
    protected $signature = 'images:webp {--force : Regenerate even if outputs already exist} {--quality=82 : WebP/JPEG quality (0-100)}';

    protected $description = 'Create WebP + responsive width variants of JPG/PNG images for fast delivery';

    /** Responsive widths to generate (only those smaller than the source). */
    protected array $widths = [480, 768, 1080, 1440];

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->components->warn('GD has no WebP support on this PHP build — skipping generation.');
            $this->line('  This is safe to ignore. WebP files are generated during local development');
            $this->line('  and deployed with the app; the site serves the original JPG/PNG when a');
            $this->line('  .webp sibling is missing. To generate WebP here, enable WebP in PHP\'s GD');
            $this->line('  extension (e.g. install libwebp + php-gd) and re-run this command.');

            return self::SUCCESS;
        }

        @ini_set('memory_limit', '512M');
        $quality = (int) $this->option('quality');
        $force = (bool) $this->option('force');

        $dirs = array_filter([
            public_path('images'),
            storage_path('app/public'),
        ], 'is_dir');

        $webp = 0;
        $variants = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                $src = $file->getPathname();

                // Don't treat a generated variant (name-480.jpg) as a source.
                if (preg_match('/-\d{2,4}\.(jpe?g|png)$/i', $src)) {
                    continue;
                }

                $img = $this->load($src);
                if (! $img) {
                    $failed++;
                    $this->line('  <error>✗</error> '.$this->rel($src));

                    continue;
                }

                $srcW = imagesx($img);
                $srcH = imagesy($img);

                // 1) WebP sibling of the original.
                $originalWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
                if ($force || ! is_file($originalWebp) || filemtime($originalWebp) < filemtime($src)) {
                    @imagewebp($img, $originalWebp, $quality) ? $webp++ : $failed++;
                }

                // 2) Resized width variants + their webp.
                foreach ($this->widths as $w) {
                    if ($w >= $srcW) {
                        continue; // never upscale
                    }
                    $variantJpg = preg_replace('/(\.(?:jpe?g|png))$/i', "-{$w}\$1", $src);
                    $variantWebp = preg_replace('/\.(jpe?g|png)$/i', "-{$w}.webp", $src);

                    if (! $force && is_file($variantJpg) && filemtime($variantJpg) >= filemtime($src)) {
                        $skipped++;

                        continue;
                    }

                    $resized = $this->resize($img, $srcW, $srcH, $w);
                    $isPng = str_ends_with(strtolower($variantJpg), '.png');

                    $ok = $isPng ? @imagepng($resized, $variantJpg, 6) : @imagejpeg($resized, $variantJpg, $quality);
                    $ok = @imagewebp($resized, $variantWebp, $quality) && $ok;

                    $ok ? $variants++ : $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Done — {$webp} webp, {$variants} variants, {$skipped} up-to-date, {$failed} failed.");

        return self::SUCCESS;
    }

    /** Load an image by its real format (extension can lie, e.g. a JPEG named .png). */
    protected function load(string $src)
    {
        try {
            $mime = (@getimagesize($src)['mime']) ?? '';
            $img = match ($mime) {
                'image/png' => @imagecreatefrompng($src),
                'image/jpeg' => @imagecreatefromjpeg($src),
                'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($src) : null,
                default => null,
            };
            if ($img && $mime === 'image/png') {
                imagepalettetotruecolor($img);
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }

            return $img ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function resize($img, int $srcW, int $srcH, int $w)
    {
        $h = (int) round($srcH * $w / $srcW);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $srcW, $srcH);

        return $dst;
    }

    protected function rel(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
