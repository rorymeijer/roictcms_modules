<?php
defined('ROICT_CMS') or die('No direct access');

class SocialFeedModule
{
    private static array $platformIcons = [
        'instagram' => 'instagram',
        'twitter'   => 'twitter-x',
        'facebook'  => 'facebook',
        'linkedin'  => 'linkedin',
    ];

    private static array $platformColors = [
        'instagram' => '#E1306C',
        'twitter'   => '#000000',
        'facebook'  => '#1877F2',
        'linkedin'  => '#0A66C2',
    ];

    public static function init(): void
    {
        add_action('admin_sidebar_nav', [self::class, 'sidebarNav']);
        add_action('theme_head', [self::class, 'loadAssets']);
        add_shortcode('social_feed', [self::class, 'shortcodeHandler']);
    }

    public static function sidebarNav(): void
    {
        $url = BASE_URL . '/modules/social-feed/admin/';
        echo '<a class="nav-link" href="' . $url . '">'
            . '<i class="bi bi-rss me-2"></i>Social Feed</a>';
    }

    public static function loadAssets(): void
    {
        $cssUrl = BASE_URL . '/modules/social-feed/assets/css/social-feed.css';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    }

    public static function shortcodeHandler(array $attrs): string
    {
        $platform = isset($attrs['platform']) ? trim($attrs['platform']) : null;
        return self::renderFeed($platform);
    }

    public static function renderFeed(?string $platform = null): string
    {
        $db = Database::getInstance();

        $sql = "SELECT * FROM " . DB_PREFIX . "social_feed_posts WHERE status = 'active'";
        $params = [];

        if ($platform !== null) {
            $allowed = ['instagram', 'twitter', 'facebook', 'linkedin'];
            if (in_array($platform, $allowed, true)) {
                $sql .= " AND platform = ?";
                $params[] = $platform;
            }
        }

        $sql .= " ORDER BY sort_order ASC, posted_at DESC";
        $stmt = $db->query($sql, $params);
        $posts = $stmt->fetchAll();

        if (empty($posts)) {
            return '<p class="text-muted">Geen social posts gevonden.</p>';
        }

        $html = '<div class="sf-grid">';
        foreach ($posts as $post) {
            $icon = self::$platformIcons[$post['platform']] ?? 'share';
            $color = self::$platformColors[$post['platform']] ?? '#333';
            $postedAt = $post['posted_at'] ? date('d-m-Y', strtotime($post['posted_at'])) : '';

            $html .= '<div class="sf-card">';

            if (!empty($post['image_url'])) {
                $html .= '<div class="sf-card-img">'
                    . '<img src="' . htmlspecialchars($post['image_url'], ENT_QUOTES, 'UTF-8') . '"'
                    . ' alt="' . htmlspecialchars($post['platform'], ENT_QUOTES, 'UTF-8') . ' post">'
                    . '</div>';
            }

            $html .= '<div class="sf-card-body">';
            $html .= '<div class="sf-platform" style="color:' . $color . ';">'
                . '<i class="bi bi-' . $icon . '"></i>'
                . ' ' . ucfirst(htmlspecialchars($post['platform'], ENT_QUOTES, 'UTF-8'))
                . '</div>';

            if (!empty($post['post_text'])) {
                $html .= '<p class="sf-text">' . htmlspecialchars($post['post_text'], ENT_QUOTES, 'UTF-8') . '</p>';
            }

            if ($postedAt) {
                $html .= '<div class="sf-date"><i class="bi bi-calendar3"></i> ' . $postedAt . '</div>';
            }

            if (!empty($post['post_url'])) {
                $html .= '<a href="' . htmlspecialchars($post['post_url'], ENT_QUOTES, 'UTF-8') . '"'
                    . ' class="sf-link" target="_blank" rel="noopener noreferrer">'
                    . '<i class="bi bi-box-arrow-up-right"></i> Bekijk post</a>';
            }

            $html .= '</div>'; // sf-card-body
            $html .= '</div>'; // sf-card
        }
        $html .= '</div>'; // sf-grid

        return $html;
    }
}

SocialFeedModule::init();
