<?php
/**
 * Video Gallery Module - Init
 * Registreert hooks, shortcodes en de VideoGalleryModule klasse.
 */

class VideoGalleryModule
{
    /**
     * Converteert een YouTube of Vimeo URL naar een embed URL.
     */
    public static function getEmbedUrl(string $url): string
    {
        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        // Onbekend: geef originele URL terug
        return $url;
    }

    /**
     * Rendert een videogalerij grid voor de opgegeven groep.
     */
    public static function renderGallery(string $group = 'default'): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "video_gallery_items WHERE status = 'active' AND gallery_group = ? ORDER BY sort_order ASC, id ASC", [$group]);
        $videos = $stmt->fetchAll();

        if (empty($videos)) {
            return '<p class="text-muted">Geen video\'s beschikbaar.</p>';
        }

        $html = '<div class="roict-video-gallery row g-4">';
        foreach ($videos as $video) {
            $embedUrl = self::getEmbedUrl($video['video_url']);
            $html .= '<div class="col-12 col-sm-6 col-lg-4">';
            $html .= '<div class="card h-100 shadow-sm roict-video-card">';
            $html .= '<div class="ratio ratio-16x9">';
            $html .= '<iframe src="' . e($embedUrl) . '" title="' . e($video['title']) . '" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy"></iframe>';
            $html .= '</div>';
            $html .= '<div class="card-body">';
            $html .= '<h5 class="card-title">' . e($video['title']) . '</h5>';
            if (!empty($video['description'])) {
                $html .= '<p class="card-text text-muted small">' . e($video['description']) . '</p>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

// Sidebar navigatie link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/video-gallery/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-play-btn me-2"></i>Video Gallery'
        . '</a></li>';
});

// Laad CSS in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/video-gallery/assets/css/video-gallery.css">';
});

// Registreer shortcode [video_gallery] met optionele groep-parameter
add_shortcode('video_gallery', function ($atts) {
    $group = isset($atts['group']) ? (string)$atts['group'] : 'default';
    return VideoGalleryModule::renderGallery($group);
});
