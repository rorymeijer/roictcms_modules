<?php
defined('ROICT_CMS') or die('No direct access');

class RssImporterModule
{
    public static function init(): void
    {
        add_action('admin_sidebar_nav', [self::class, 'sidebarNav']);
        add_shortcode('rss_feed', [self::class, 'shortcodeHandler']);
    }

    public static function sidebarNav(): void
    {
        $url = BASE_URL . '/modules/rss-importer/admin/';
        echo '<a class="nav-link" href="' . $url . '">'
            . '<i class="bi bi-rss-fill me-2"></i>RSS Importer</a>';
    }

    public static function shortcodeHandler(array $attrs): string
    {
        $id = isset($attrs['id']) ? (int)$attrs['id'] : 0;
        if ($id <= 0) {
            return '<p class="text-danger">RSS Feed: geen geldig id opgegeven.</p>';
        }
        return self::renderFeed($id);
    }

    public static function renderFeed(int $feedId): string
    {
        $db = Database::getInstance();

        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "rss_feeds WHERE id = ? AND status = 'active' LIMIT 1", [$feedId]);
        $feed = $stmt->fetch();

        if (!$feed) {
            return '<p class="text-warning">RSS Feed: feed niet gevonden.</p>';
        }

        $cacheKey   = 'rss_cache_' . $feedId;
        $cachedData = Settings::get($cacheKey);
        $items      = [];

        if (!empty($cachedData)) {
            $decoded = json_decode($cachedData, true);
            if (
                is_array($decoded)
                && isset($decoded['timestamp'], $decoded['items'])
                && (time() - (int)$decoded['timestamp']) < ((int)$feed['cache_minutes'] * 60)
            ) {
                $items = $decoded['items'];
            }
        }

        if (empty($items)) {
            $items = self::fetchRss($feed['feed_url'], (int)$feed['max_items']);
            Settings::set($cacheKey, json_encode([
                'timestamp' => time(),
                'items'     => $items,
            ]));
        }

        if (empty($items)) {
            return '<p class="text-muted">Geen RSS-items gevonden voor: ' . htmlspecialchars($feed['title'], ENT_QUOTES, 'UTF-8') . '.</p>';
        }

        $html = '<div class="rss-feed-list">';
        $html .= '<h4 class="rss-feed-title mb-3">' . htmlspecialchars($feed['title'], ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '<ul class="list-group list-group-flush">';

        foreach ($items as $item) {
            $html .= '<li class="list-group-item px-0">';
            $html .= '<div class="d-flex justify-content-between align-items-start">';
            $html .= '<div>';

            if (!empty($item['link'])) {
                $html .= '<a href="' . htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') . '"'
                    . ' target="_blank" rel="noopener noreferrer" class="fw-semibold text-decoration-none">'
                    . htmlspecialchars($item['title'] ?? 'Zonder titel', ENT_QUOTES, 'UTF-8')
                    . '</a>';
            } else {
                $html .= '<span class="fw-semibold">'
                    . htmlspecialchars($item['title'] ?? 'Zonder titel', ENT_QUOTES, 'UTF-8')
                    . '</span>';
            }

            if (!empty($item['description'])) {
                $desc = strip_tags($item['description']);
                $desc = mb_strimwidth($desc, 0, 160, '...');
                $html .= '<p class="text-muted small mb-1 mt-1">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
            }

            if (!empty($item['pubDate'])) {
                $timestamp = strtotime($item['pubDate']);
                $formatted = $timestamp ? date('d-m-Y', $timestamp) : htmlspecialchars($item['pubDate'], ENT_QUOTES, 'UTF-8');
                $html .= '<small class="text-muted"><i class="bi bi-calendar3"></i> ' . $formatted . '</small>';
            }

            $html .= '</div>';

            if (!empty($item['link'])) {
                $html .= '<a href="' . htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') . '"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' class="btn btn-sm btn-outline-primary ms-3 text-nowrap">'
                    . '<i class="bi bi-box-arrow-up-right"></i> Lees meer</a>';
            }

            $html .= '</div>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    private static function fetchRss(string $url, int $maxItems): array
    {
        $items = [];

        $context = stream_context_create([
            'http' => [
                'timeout'    => 10,
                'user_agent' => 'ROICT CMS RSS Importer/1.0',
            ],
        ]);

        $xml = @file_get_contents($url, false, $context);

        if ($xml === false) {
            return $items;
        }

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml);

        if ($rss === false) {
            return $items;
        }

        $count = 0;
        foreach ($rss->channel->item as $item) {
            if ($count >= $maxItems) {
                break;
            }

            $items[] = [
                'title'       => (string)$item->title,
                'link'        => (string)$item->link,
                'description' => (string)$item->description,
                'pubDate'     => (string)$item->pubDate,
            ];

            $count++;
        }

        return $items;
    }
}

RssImporterModule::init();
