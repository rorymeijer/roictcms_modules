<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class EventsModule
{
    public static function renderEvents(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT * FROM " . DB_PREFIX . "events
            WHERE status = 'active' AND start_date >= NOW()
            ORDER BY start_date ASC
        ");
        $events = $stmt->fetchAll();

        if (empty($events)) {
            return '<p class="events-empty">Er zijn momenteel geen aankomende evenementen.</p>';
        }

        $html = '<div class="events-list row g-4">';
        foreach ($events as $event) {
            $startDate = new DateTime($event['start_date']);
            $html .= '<div class="col-md-6 col-lg-4">';
            $html .= '<div class="card event-card h-100">';
            if (!empty($event['image_url'])) {
                $html .= '<img src="' . e($event['image_url']) . '" class="card-img-top event-card-img" alt="' . e($event['title']) . '">';
            }
            $html .= '<div class="card-body">';
            $html .= '<div class="event-date-badge">';
            $html .= '<span class="event-day">' . $startDate->format('d') . '</span>';
            $html .= '<span class="event-month">' . $startDate->format('M') . '</span>';
            $html .= '</div>';
            $html .= '<h5 class="card-title mt-2">' . e($event['title']) . '</h5>';
            if (!empty($event['location'])) {
                $html .= '<p class="event-location"><i class="bi bi-geo-alt"></i> ' . e($event['location']) . '</p>';
            }
            $html .= '<p class="event-time"><i class="bi bi-clock"></i> ' . $startDate->format('H:i');
            if (!empty($event['end_date'])) {
                $endDate = new DateTime($event['end_date']);
                $html .= ' - ' . $endDate->format('H:i');
            }
            $html .= '</p>';
            if (!empty($event['description'])) {
                $html .= '<p class="card-text">' . nl2br(e($event['description'])) . '</p>';
            }
            if (!empty($event['registration_url'])) {
                $html .= '<a href="' . e($event['registration_url']) . '" class="btn btn-primary btn-sm mt-2" target="_blank" rel="noopener">Aanmelden</a>';
            }
            $html .= '</div></div></div>';
        }
        $html .= '</div>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/events/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-calendar-event me-2"></i>Evenementen</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/events/assets/css/events.css">';
});

// Register shortcode
add_shortcode('events', function ($atts) {
    return EventsModule::renderEvents();
});
