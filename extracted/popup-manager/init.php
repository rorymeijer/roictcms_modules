<?php
defined('ROICT_CMS') or die('No direct access');

class PopupManagerModule
{
    public static function init(): void
    {
        add_action('admin_sidebar_nav', [self::class, 'sidebarNav']);
        add_action('theme_head', [self::class, 'loadStyles']);
        add_action('theme_footer', [self::class, 'renderPopupsAndScripts']);
    }

    public static function sidebarNav(): void
    {
        $url = BASE_URL . '/modules/popup-manager/admin/';
        echo '<a class="nav-link" href="' . $url . '">'
            . '<i class="bi bi-window-stack me-2"></i>Popup Manager</a>';
    }

    public static function loadStyles(): void
    {
        $cssUrl = BASE_URL . '/modules/popup-manager/assets/css/popup-manager.css';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    }

    public static function renderPopupsAndScripts(): void
    {
        $html = self::renderPopups();
        if (!empty($html)) {
            echo $html;
            $jsUrl = BASE_URL . '/modules/popup-manager/assets/js/popup-manager.js';
            echo '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }
    }

    public static function renderPopups(): string
    {
        $db = Database::getInstance();

        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "popups WHERE status = 'active' ORDER BY id ASC");
        $popups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($popups)) {
            return '';
        }

        $html = '';
        foreach ($popups as $popup) {
            $trigger    = htmlspecialchars($popup['trigger_type'], ENT_QUOTES, 'UTF-8');
            $delay      = (int)$popup['trigger_delay'];
            $cookieDays = (int)$popup['cookie_days'];
            $showOnce   = (int)$popup['show_once'];
            $id         = (int)$popup['id'];

            $html .= '<div class="pm-popup" style="display:none;"'
                . ' data-trigger="' . $trigger . '"'
                . ' data-delay="' . $delay . '"'
                . ' data-cookie-days="' . $cookieDays . '"'
                . ' data-show-once="' . $showOnce . '"'
                . ' data-popup-id="' . $id . '">'
                . '<div class="pm-popup-overlay"></div>'
                . '<div class="pm-popup-dialog">'
                . '<button class="pm-popup-close" aria-label="Sluiten">'
                . '<i class="bi bi-x-lg"></i>'
                . '</button>'
                . '<div class="pm-popup-content">'
                . $popup['content']
                . '</div>'
                . '</div>'
                . '</div>' . PHP_EOL;
        }

        return $html;
    }
}

PopupManagerModule::init();
