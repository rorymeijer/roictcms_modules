<?php
/**
 * Team Module - Init
 * Registreert hooks, shortcodes en de TeamModule klasse.
 */

class TeamModule
{
    /**
     * Haalt actieve teamleden op en rendert als responsive cards grid.
     */
    public static function renderTeam(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "team_members WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        $members = $stmt->fetchAll();

        if (empty($members)) {
            return '<p class="text-muted">Geen teamleden beschikbaar.</p>';
        }

        $html = '<div class="roict-team row g-4">';
        foreach ($members as $member) {
            $html .= '<div class="col-12 col-sm-6 col-lg-3">';
            $html .= '<div class="card h-100 text-center shadow-sm roict-team-card">';
            $html .= '<div class="card-body">';

            // Foto of initialen avatar
            if (!empty($member['photo_url'])) {
                $html .= '<img src="' . e($member['photo_url']) . '" alt="' . e($member['name']) . '" class="roict-team-photo rounded-circle mb-3">';
            } else {
                $initials = mb_strtoupper(mb_substr($member['name'], 0, 1));
                $html .= '<div class="roict-team-avatar rounded-circle mx-auto mb-3 bg-primary text-white d-flex align-items-center justify-content-center">';
                $html .= $initials;
                $html .= '</div>';
            }

            $html .= '<h5 class="card-title mb-1">' . e($member['name']) . '</h5>';

            if (!empty($member['role'])) {
                $html .= '<p class="text-primary fw-semibold small mb-2">' . e($member['role']) . '</p>';
            }

            if (!empty($member['bio'])) {
                $html .= '<p class="card-text text-muted small">' . e($member['bio']) . '</p>';
            }

            // Sociale links
            $links = [];
            if (!empty($member['email'])) {
                $links[] = '<a href="mailto:' . e($member['email']) . '" class="btn btn-sm btn-outline-secondary" title="E-mail"><i class="bi bi-envelope"></i></a>';
            }
            if (!empty($member['linkedin_url'])) {
                $links[] = '<a href="' . e($member['linkedin_url']) . '" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="LinkedIn"><i class="bi bi-linkedin"></i></a>';
            }
            if (!empty($links)) {
                $html .= '<div class="mt-3 d-flex gap-2 justify-content-center">' . implode('', $links) . '</div>';
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
    $url = BASE_URL . '/modules/team/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-people me-2"></i>Team'
        . '</a></li>';
});

// Laad CSS in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/team/assets/css/team.css">';
});

// Registreer shortcode [team]
add_shortcode('team', function ($atts) {
    return TeamModule::renderTeam();
});
