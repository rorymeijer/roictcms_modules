<?php
/**
 * Testimonials Module - Init
 * Registreert hooks, shortcodes en de TestimonialsModule klasse.
 */

class TestimonialsModule
{
    /**
     * Haalt actieve testimonials op en rendert als Bootstrap cards.
     */
    public static function renderTestimonials(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "testimonials WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        $testimonials = $stmt->fetchAll();

        if (empty($testimonials)) {
            return '<p class="text-muted">Geen testimonials beschikbaar.</p>';
        }

        $html = '<div class="roict-testimonials row g-4">';
        foreach ($testimonials as $t) {
            $rating = max(1, min(5, (int)$t['rating']));
            $stars  = str_repeat('<i class="bi bi-star-fill text-warning"></i>', $rating)
                    . str_repeat('<i class="bi bi-star text-warning"></i>', 5 - $rating);

            $html .= '<div class="col-12 col-md-6 col-lg-4">';
            $html .= '<div class="card h-100 shadow-sm roict-testimonial-card">';
            $html .= '<div class="card-body">';
            $html .= '<div class="mb-2">' . $stars . '</div>';
            $html .= '<blockquote class="mb-3">';
            $html .= '<p class="fst-italic text-muted">&ldquo;' . e($t['quote']) . '&rdquo;</p>';
            $html .= '</blockquote>';
            $html .= '<div class="d-flex align-items-center mt-auto">';
            if (!empty($t['avatar_url'])) {
                $html .= '<img src="' . e($t['avatar_url']) . '" alt="' . e($t['name']) . '" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;">';
            } else {
                $html .= '<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;font-size:1.2rem;font-weight:700;">';
                $html .= e(mb_strtoupper(mb_substr($t['name'], 0, 1)));
                $html .= '</div>';
            }
            $html .= '<div>';
            $html .= '<strong class="d-block">' . e($t['name']) . '</strong>';
            if (!empty($t['company'])) {
                $html .= '<small class="text-muted">' . e($t['company']) . '</small>';
            }
            $html .= '</div>';
            $html .= '</div>';
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
    $url = BASE_URL . '/modules/testimonials/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-chat-quote me-2"></i>Testimonials'
        . '</a></li>';
});

// Laad CSS in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/testimonials/assets/css/testimonials.css">';
});

// Registreer shortcode [testimonials]
add_shortcode('testimonials', function ($atts) {
    return TestimonialsModule::renderTestimonials();
});
