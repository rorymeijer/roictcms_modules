<?php
defined('ROICT_CMS') or die('No direct access');

class GoogleMapsModule
{
    public static function init(): void
    {
        add_action('admin_sidebar_nav', [self::class, 'sidebarNav']);
        add_shortcode('google_map', [self::class, 'shortcodeHandler']);
    }

    public static function sidebarNav(): void
    {
        $url = BASE_URL . '/modules/google-maps/admin/';
        echo '<a class="nav-link" href="' . $url . '">'
            . '<i class="bi bi-geo-alt me-2"></i>Google Maps</a>';
    }

    public static function shortcodeHandler(array $attrs): string
    {
        $id = isset($attrs['id']) ? (int)$attrs['id'] : 0;
        if ($id <= 0) {
            return '<p class="text-danger">Google Maps: geen geldig id opgegeven.</p>';
        }
        return self::renderMap($id);
    }

    public static function renderMap(int $locationId): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "google_maps_locations WHERE id = ? AND status = 'active' LIMIT 1", [$locationId]);
        $location = $stmt->fetch();

        if (!$location) {
            return '<p class="text-warning">Google Maps: locatie niet gevonden.</p>';
        }

        $apiKey = Settings::get('google_maps_api_key');
        if (empty($apiKey)) {
            return '<div class="alert alert-info">Google Maps: Voeg een API key toe in instellingen.</div>';
        }

        $encodedAddress = urlencode($location['address']);
        $zoom = (int)$location['zoom_level'];
        $width = e($location['map_width']);
        $height = (int)$location['map_height'];

        $src = "https://www.google.com/maps/embed/v1/place?key={$apiKey}&q={$encodedAddress}&zoom={$zoom}";

        return '<div class="google-maps-embed">'
            . '<iframe'
            . ' width="' . $width . '"'
            . ' height="' . $height . '"'
            . ' style="border:0;"'
            . ' loading="lazy"'
            . ' allowfullscreen'
            . ' referrerpolicy="no-referrer-when-downgrade"'
            . ' src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">'
            . '</iframe>'
            . '</div>';
    }
}

GoogleMapsModule::init();
