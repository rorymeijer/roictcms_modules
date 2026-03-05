<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class ReservationsModule
{
    public static function renderForm(): string
    {
        $db = Database::getInstance();

        // Fetch available slots (future dates with capacity remaining)
        $stmt = $db->query("
            SELECT s.*,
                   (s.max_reservations - COALESCE(COUNT(r.id), 0)) AS available
            FROM " . DB_PREFIX . "reservation_slots s
            LEFT JOIN " . DB_PREFIX . "reservations r
                ON r.slot_id = s.id AND r.status != 'cancelled'
            WHERE s.slot_date >= CURDATE()
            GROUP BY s.id
            HAVING available > 0
            ORDER BY s.slot_date ASC, s.slot_time ASC
        ");
        $slots = $stmt->fetchAll();

        if (empty($slots)) {
            return '<p class="reservations-empty">Er zijn momenteel geen beschikbare tijdslots.</p>';
        }

        $html = '<div class="reservation-widget">';
        $html .= '<form method="post" class="reservation-form">';
        $html .= csrf_field();
        $html .= '<input type="hidden" name="_action" value="_reservation_action">';

        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label fw-bold">Kies een tijdslot <span class="text-danger">*</span></label>';
        $html .= '<div class="reservation-slots-list">';
        foreach ($slots as $slot) {
            $id = (int)$slot['id'];
            $date = date('d-m-Y', strtotime($slot['slot_date']));
            $time = substr($slot['slot_time'], 0, 5);
            $label = $date . ' om ' . $time;
            if (!empty($slot['title'])) {
                $label = e($slot['title']) . ' — ' . $label;
            }
            $html .= '<div class="form-check reservation-slot-item">';
            $html .= '<input class="form-check-input" type="radio" name="slot_id" id="slot-' . $id . '" value="' . $id . '" required>';
            $html .= '<label class="form-check-label" for="slot-' . $id . '">';
            $html .= $label;
            $html .= ' <span class="badge bg-success">' . (int)$slot['available'] . ' plek(ken) vrij</span>';
            $html .= '</label>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6"><label class="form-label">Naam <span class="text-danger">*</span></label>';
        $html .= '<input type="text" name="name" class="form-control" required></div>';
        $html .= '<div class="col-md-6"><label class="form-label">E-mailadres <span class="text-danger">*</span></label>';
        $html .= '<input type="email" name="email" class="form-control" required></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Telefoonnummer</label>';
        $html .= '<input type="tel" name="phone" class="form-control"></div>';
        $html .= '<div class="col-12"><label class="form-label">Opmerkingen</label>';
        $html .= '<textarea name="notes" class="form-control" rows="3"></textarea></div>';
        $html .= '<div class="col-12"><button type="submit" class="btn btn-primary">Reservering plaatsen</button></div>';
        $html .= '</div>';

        $html .= '</form></div>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/reservations/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-calendar-check me-2"></i>Reserveringen</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/reservations/assets/css/reservations.css">';
});

// Register shortcode
add_shortcode('reservation_form', function ($atts) {
    return ReservationsModule::renderForm();
});

// POST handler for reservations
add_action('init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === '_reservation_action') {
        csrf_verify();

        $slotId = (int)($_POST['slot_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($slotId > 0 && !empty($name) && !empty($email)) {
            $db = Database::getInstance();

            // Check capacity
            $stmt = $db->query("
                SELECT s.max_reservations,
                       COALESCE(COUNT(r.id), 0) AS current_count
                FROM " . DB_PREFIX . "reservation_slots s
                LEFT JOIN " . DB_PREFIX . "reservations r
                    ON r.slot_id = s.id AND r.status != 'cancelled'
                WHERE s.id = ?
                GROUP BY s.id
            ", [$slotId]);
            $slotData = $stmt->fetch();

            if ($slotData && $slotData['current_count'] < $slotData['max_reservations']) {
                $stmt = $db->query("
                    INSERT INTO " . DB_PREFIX . "reservations
                        (slot_id, name, email, phone, notes, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$slotId, $name, $email, $phone, $notes, $ip]);
                flash('success', 'Uw reservering is succesvol geplaatst. U ontvangt een bevestiging per e-mail.');
            } else {
                flash('error', 'Dit tijdslot is helaas al vol. Kies een ander tijdslot.');
            }
        } else {
            flash('error', 'Vul alle verplichte velden in en selecteer een tijdslot.');
        }

        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }
});
