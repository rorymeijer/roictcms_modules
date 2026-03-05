<?php
/**
 * Cache Manager Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class CacheManager
{
    private static string $cacheDir = '';

    private static function dir(): string
    {
        if (self::$cacheDir === '') {
            self::$cacheDir = BASE_PATH . '/cache/pages/';
        }
        return self::$cacheDir;
    }

    public static function getCacheKey(string $url): string
    {
        return md5($url) . '.html';
    }

    public static function get(string $url): ?string
    {
        $file = self::dir() . self::getCacheKey($url);
        if (!file_exists($file)) {
            return null;
        }

        $db  = Database::getInstance();
        $ttl = (int) ($db->fetchOne(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_ttl'"
        ) ?: 3600);

        if ((time() - filemtime($file)) > $ttl) {
            @unlink($file);
            return null;
        }

        return file_get_contents($file) ?: null;
    }

    public static function set(string $url, string $content): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . self::getCacheKey($url), $content, LOCK_EX);
    }

    public static function flush(): int
    {
        $dir   = self::dir();
        $count = 0;
        if (!is_dir($dir)) return 0;
        foreach (glob($dir . '*.html') as $file) {
            if (@unlink($file)) $count++;
        }
        return $count;
    }

    public static function flushUrl(string $url): bool
    {
        $file = self::dir() . self::getCacheKey($url);
        return file_exists($file) && @unlink($file);
    }

    public static function getCacheSize(): array
    {
        $dir   = self::dir();
        $count = 0;
        $size  = 0;
        if (is_dir($dir)) {
            foreach (glob($dir . '*.html') as $file) {
                $count++;
                $size += filesize($file);
            }
        }
        return ['count' => $count, 'size' => $size];
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

// Activeer cache output buffering als ingeschakeld
add_action('init', function () {
    $db = Database::getInstance();
    $enabled = $db->fetchOne(
        "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_enabled'"
    );

    if ($enabled !== '1') return;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;

    $url     = $_SERVER['REQUEST_URI'] ?? '/';
    $exclude = $db->fetchOne(
        "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_exclude'"
    ) ?: '/admin';

    // Controleer uitgesloten URL's
    foreach (array_filter(array_map('trim', explode("\n", $exclude))) as $excl) {
        if ($excl && strpos($url, $excl) === 0) return;
    }

    // Probeer cache te serveren
    $cached = CacheManager::get($url);
    if ($cached !== null) {
        header('X-Cache: HIT');
        echo $cached;
        exit();
    }

    // Start output buffering om response op te slaan
    ob_start(function (string $buffer) use ($url): string {
        if (strlen($buffer) > 0) {
            CacheManager::set($url, $buffer);
        }
        return $buffer;
    });
});

// Sidebar
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/cache-manager/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-lightning me-2"></i>Cache Manager'
        . '</a></li>';
});
