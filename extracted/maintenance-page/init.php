<?php
/**
 * Onderhoudspagina Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class MaintenancePageModule
{
    private static function getSetting(string $key, string $default = ''): string
    {
        $db = Database::getInstance();
        $val = $db->fetchOne(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?",
            [$key]
        );
        return ($val !== false && $val !== null) ? (string) $val : $default;
    }

    public static function render(): void
    {
        http_response_code(503);
        header('Retry-After: 3600');

        $title    = self::getSetting('maintenance_page_title', 'Even geduld...');
        $message  = self::getSetting('maintenance_page_message', 'We zijn bezig met onderhoud. Kom snel terug!');
        $bgColor  = self::getSetting('maintenance_page_bg_color', '#1a1a2e');
        $txtColor = self::getSetting('maintenance_page_text_color', '#ffffff');
        $showMail = self::getSetting('maintenance_page_show_email', '0') === '1';
        $email    = self::getSetting('maintenance_page_email', '');

        $titleEsc   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $bgEsc      = htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8');
        $txtEsc     = htmlspecialchars($txtColor, ENT_QUOTES, 'UTF-8');
        $emailEsc   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $titleEsc . '</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: ' . $bgEsc . ';
            color: ' . $txtEsc . ';
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .container { max-width: 600px; }
        .icon { font-size: 4rem; margin-bottom: 1.5rem; opacity: .85; }
        h1 { font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700; }
        p { font-size: 1.1rem; line-height: 1.7; opacity: .85; }
        a { color: inherit; text-decoration: underline; }
        .email-block { margin-top: 1.5rem; font-size: 0.95rem; opacity: .75; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>' . $titleEsc . '</h1>
        <p>' . $messageEsc . '</p>';

        if ($showMail && $email) {
            echo '<div class="email-block">Vragen? Mail naar <a href="mailto:' . $emailEsc . '">' . $emailEsc . '</a></div>';
        }

        echo '    </div>
</body>
</html>';
        exit();
    }
}

// Controleer onderhoudsmodus bij elke request
add_action('init', function () {
    $db      = Database::getInstance();
    $enabled = $db->fetchOne(
        "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'maintenance_page_enabled'"
    );

    if ($enabled !== '1') return;

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
    $path = $basePath ? substr($uri, strlen($basePath)) : $uri;

    // Admin URL's overslaan
    if (strpos($path, '/admin') === 0) return;

    // Admin-gebruikers zien de echte site
    if (function_exists('Auth') || class_exists('Auth')) {
        if (Auth::isLoggedIn() && method_exists('Auth', 'isAdmin') && Auth::isAdmin()) {
            return;
        }
    }

    MaintenancePageModule::render();
});

// Sidebar
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/maintenance-page/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-tools me-2"></i>Onderhoudspagina'
        . '</a></li>';
});
