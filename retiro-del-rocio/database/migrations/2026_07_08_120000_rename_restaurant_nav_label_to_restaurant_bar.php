<?php

use App\Models\SiteContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Restaurant → Restaurant/Bar, and /restaurant → /restaurant-bar.
 *
 * The navbar is CMS-driven: its links live in the `site_contents` row keyed
 * `nav.links`, which takes precedence over the default in config/cms.php
 * (see cms_array() in app/helpers.php). Editing the config alone therefore
 * leaves an already-seeded site unchanged, so the stored row is rewritten too.
 *
 * The old /restaurant path still resolves — routes/web.php keeps it as a
 * permanent redirect — so this is safe even if it runs before the deploy.
 */
return new class extends Migration
{
    private const OLD = ['label' => 'Restaurant', 'url' => '/restaurant'];

    private const NEW = ['label' => 'Restaurant/Bar', 'url' => '/restaurant-bar'];

    public function up(): void
    {
        $this->rewrite(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW, self::OLD);
    }

    /**
     * Matched on the URL alone, so a link an admin has already relabelled by
     * hand still gets its URL corrected rather than being skipped.
     */
    private function rewrite(array $from, array $to): void
    {
        $row = SiteContent::where('key', 'nav.links')->first();

        if (! $row) {
            return; // never seeded — config/cms.php default already carries the new values
        }

        $links = json_decode((string) $row->value, true);

        if (! is_array($links)) {
            return;
        }

        $changed = false;

        foreach ($links as $i => $link) {
            if (($link['url'] ?? null) !== $from['url']) {
                continue;
            }

            $links[$i]['url'] = $to['url'];

            // Only touch the label if it is still the one we shipped.
            if (($link['label'] ?? null) === $from['label']) {
                $links[$i]['label'] = $to['label'];
            }

            $changed = true;
        }

        if ($changed) {
            SiteContent::put('nav.links', json_encode($links, JSON_UNESCAPED_UNICODE));
        }
    }
};
