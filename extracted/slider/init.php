<?php
/**
 * Slider Module - Init
 * Registreert hooks, shortcodes en de SliderModule klasse.
 */

class SliderModule
{
    /**
     * Haalt actieve slides op en rendert een Bootstrap carousel.
     */
    public static function renderSlider(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "slider_items WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        $slides = $stmt->fetchAll();

        if (empty($slides)) {
            return '';
        }

        $id = 'sliderCarousel' . uniqid();
        $html = '<div id="' . $id . '" class="carousel slide roict-slider" data-bs-ride="carousel">';

        // Indicators
        $html .= '<div class="carousel-indicators">';
        foreach ($slides as $i => $slide) {
            $active = $i === 0 ? ' class="active" aria-current="true"' : '';
            $html .= '<button type="button" data-bs-target="#' . $id . '" data-bs-slide-to="' . $i . '"' . $active . ' aria-label="Slide ' . ($i + 1) . '"></button>';
        }
        $html .= '</div>';

        // Items
        $html .= '<div class="carousel-inner">';
        foreach ($slides as $i => $slide) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="carousel-item' . $active . '">';
            $html .= '<img src="' . e($slide['image_path']) . '" class="d-block w-100" alt="' . e($slide['title']) . '">';
            if (!empty($slide['title']) || !empty($slide['subtitle']) || !empty($slide['button_text'])) {
                $html .= '<div class="carousel-caption d-none d-md-block">';
                if (!empty($slide['title'])) {
                    $html .= '<h5>' . e($slide['title']) . '</h5>';
                }
                if (!empty($slide['subtitle'])) {
                    $html .= '<p>' . e($slide['subtitle']) . '</p>';
                }
                if (!empty($slide['button_text']) && !empty($slide['button_url'])) {
                    $html .= '<a href="' . e($slide['button_url']) . '" class="btn btn-primary">' . e($slide['button_text']) . '</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Controls
        $html .= '<button class="carousel-control-prev" type="button" data-bs-target="#' . $id . '" data-bs-slide="prev">';
        $html .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
        $html .= '<span class="visually-hidden">Vorige</span>';
        $html .= '</button>';
        $html .= '<button class="carousel-control-next" type="button" data-bs-target="#' . $id . '" data-bs-slide="next">';
        $html .= '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
        $html .= '<span class="visually-hidden">Volgende</span>';
        $html .= '</button>';

        $html .= '</div>';

        return $html;
    }
}

// Sidebar navigatie link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/slider/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-images me-2"></i>Slider'
        . '</a></li>';
});

// Laad CSS in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/slider/assets/css/slider.css">';
});

// Registreer shortcode [slider]
add_shortcode('slider', function ($atts) {
    return SliderModule::renderSlider();
});
