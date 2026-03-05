<?php
/**
 * REST API Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class RestApiModule
{
    private static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    private static function error(string $message, int $code): void
    {
        self::json(['error' => $message, 'code' => $code], $code);
    }

    /**
     * Valideer de API key en geef het record terug, of null bij mislukking.
     */
    private static function validateApiKey(): ?array
    {
        $db = Database::getInstance();

        // Bearer token of query parameter
        $key = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($authHeader, 'Bearer ') === 0) {
            $key = trim(substr($authHeader, 7));
        }
        if ($key === '') {
            $key = trim($_GET['api_key'] ?? '');
        }

        if ($key === '') {
            return null;
        }

        $record = $db->fetchRow(
            "SELECT * FROM " . DB_PREFIX . "api_keys WHERE api_key = ? AND status = 'active'",
            [$key]
        );

        if (!$record) {
            return null;
        }

        // Bijwerken last_used
        $db->query(
            "UPDATE " . DB_PREFIX . "api_keys SET last_used = NOW() WHERE id = ?",
            [(int) $record['id']]
        );

        return $record;
    }

    private static function hasPermission(array $keyRecord, string $permission): bool
    {
        $perms = json_decode($keyRecord['permissions'] ?? '[]', true);
        return is_array($perms) && in_array($permission, $perms, true);
    }

    public static function handleRequest(): void
    {
        $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Verwijder base path prefix als aanwezig
        $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
        if ($basePath && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        // Verwijder /api/v1 prefix
        $route = preg_replace('#^/api/v1#', '', $path);
        $route = rtrim($route, '/') ?: '/';

        $keyRecord = self::validateApiKey();
        if (!$keyRecord) {
            self::error('Ongeldige of ontbrekende API key.', 401);
        }

        $db = Database::getInstance();

        // GET /pages
        if ($method === 'GET' && $route === '/pages') {
            if (!self::hasPermission($keyRecord, 'pages_read')) {
                self::error('Geen toegang tot pages_read.', 403);
            }
            $pages = $db->fetchAll(
                "SELECT id, title, slug, meta_title, meta_desc, created_at FROM " . DB_PREFIX . "pages WHERE status = 'published' ORDER BY id DESC"
            ) ?: [];
            self::json(['data' => $pages, 'count' => count($pages)]);
        }

        // GET /pages/{slug}
        if ($method === 'GET' && preg_match('#^/pages/([^/]+)$#', $route, $m)) {
            if (!self::hasPermission($keyRecord, 'pages_read')) {
                self::error('Geen toegang tot pages_read.', 403);
            }
            $page = $db->fetchRow(
                "SELECT id, title, slug, content, meta_title, meta_desc, created_at FROM " . DB_PREFIX . "pages WHERE slug = ? AND status = 'published'",
                [$m[1]]
            );
            if (!$page) self::error('Pagina niet gevonden.', 404);
            self::json(['data' => $page]);
        }

        // GET /news
        if ($method === 'GET' && $route === '/news') {
            if (!self::hasPermission($keyRecord, 'news_read')) {
                self::error('Geen toegang tot news_read.', 403);
            }
            $news = $db->fetchAll(
                "SELECT id, title, slug, excerpt, published_at, created_at FROM " . DB_PREFIX . "news WHERE status = 'published' ORDER BY published_at DESC"
            ) ?: [];
            self::json(['data' => $news, 'count' => count($news)]);
        }

        // GET /news/{slug}
        if ($method === 'GET' && preg_match('#^/news/([^/]+)$#', $route, $m)) {
            if (!self::hasPermission($keyRecord, 'news_read')) {
                self::error('Geen toegang tot news_read.', 403);
            }
            $item = $db->fetchRow(
                "SELECT id, title, slug, excerpt, content, published_at, created_at FROM " . DB_PREFIX . "news WHERE slug = ? AND status = 'published'",
                [$m[1]]
            );
            if (!$item) self::error('Nieuwsbericht niet gevonden.', 404);
            self::json(['data' => $item]);
        }

        self::error('Endpoint niet gevonden.', 404);
    }
}

// Route API requests
add_action('init', function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
    if ($basePath) {
        $path = substr($path, strlen($basePath));
    }
    if (strpos($path, '/api/v1/') === 0 || $path === '/api/v1') {
        RestApiModule::handleRequest();
    }
});

// Sidebar
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/rest-api/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-code-slash me-2"></i>REST API'
        . '</a></li>';
});
