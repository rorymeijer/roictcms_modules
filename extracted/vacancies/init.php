<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class VacanciesModule
{
    public static function renderVacancies(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT * FROM " . DB_PREFIX . "vacancies
            WHERE status = 'open'
            ORDER BY created_at DESC
        ");
        $vacancies = $stmt->fetchAll();

        if (empty($vacancies)) {
            return '<p class="vacancies-empty">Er zijn momenteel geen openstaande vacatures.</p>';
        }

        $html = '<div class="vacancies-list">';
        foreach ($vacancies as $vacancy) {
            $id = (int)$vacancy['id'];
            $html .= '<div class="card vacancy-card mb-3">';
            $html .= '<div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#vacancy-' . $id . '">';
            $html .= '<div>';
            $html .= '<h5 class="mb-0">' . e($vacancy['title']) . '</h5>';
            $html .= '<small class="text-muted">';
            if (!empty($vacancy['department'])) {
                $html .= '<span class="me-3"><i class="bi bi-building me-1"></i>' . e($vacancy['department']) . '</span>';
            }
            if (!empty($vacancy['location'])) {
                $html .= '<span class="me-3"><i class="bi bi-geo-alt me-1"></i>' . e($vacancy['location']) . '</span>';
            }
            if (!empty($vacancy['employment_type'])) {
                $html .= '<span><i class="bi bi-clock me-1"></i>' . e($vacancy['employment_type']) . '</span>';
            }
            $html .= '</small>';
            $html .= '</div>';
            $html .= '<i class="bi bi-chevron-down"></i>';
            $html .= '</div>';
            $html .= '<div class="collapse" id="vacancy-' . $id . '">';
            $html .= '<div class="card-body">';
            if (!empty($vacancy['description'])) {
                $html .= '<h6>Omschrijving</h6><p>' . nl2br(e($vacancy['description'])) . '</p>';
            }
            if (!empty($vacancy['requirements'])) {
                $html .= '<h6>Vereisten</h6><p>' . nl2br(e($vacancy['requirements'])) . '</p>';
            }
            if (!empty($vacancy['salary_range'])) {
                $html .= '<p><strong>Salaris:</strong> ' . e($vacancy['salary_range']) . '</p>';
            }
            $html .= self::renderApplyForm($id);
            $html .= '</div></div></div>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function renderApplyForm(int $vacancyId): string
    {
        $html = '<hr>';
        $html .= '<h6>Solliciteer direct</h6>';
        $html .= '<form method="post" class="vacancy-apply-form" enctype="multipart/form-data">';
        $html .= csrf_field();
        $html .= '<input type="hidden" name="_action" value="_vacancy_apply">';
        $html .= '<input type="hidden" name="vacancy_id" value="' . $vacancyId . '">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6"><label class="form-label">Naam <span class="text-danger">*</span></label>';
        $html .= '<input type="text" name="name" class="form-control" required></div>';
        $html .= '<div class="col-md-6"><label class="form-label">E-mailadres <span class="text-danger">*</span></label>';
        $html .= '<input type="email" name="email" class="form-control" required></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Telefoonnummer</label>';
        $html .= '<input type="tel" name="phone" class="form-control"></div>';
        $html .= '<div class="col-12"><label class="form-label">Motivatie</label>';
        $html .= '<textarea name="motivation" class="form-control" rows="4"></textarea></div>';
        $html .= '<div class="col-12"><label class="form-label">CV (bestandsnaam)</label>';
        $html .= '<input type="text" name="cv_filename" class="form-control" placeholder="bijv. cv-jan-jansen.pdf"></div>';
        $html .= '<div class="col-12"><button type="submit" class="btn btn-primary">Sollicitatie verzenden</button></div>';
        $html .= '</div></form>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/vacancies/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-briefcase me-2"></i>Vacatures</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/vacancies/assets/css/vacancies.css">';
});

// Register shortcode
add_shortcode('vacancies', function ($atts) {
    return VacanciesModule::renderVacancies();
});

// POST handler for applications
add_action('init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === '_vacancy_apply') {
        csrf_verify();

        $vacancyId = (int)($_POST['vacancy_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $motivation = trim($_POST['motivation'] ?? '');
        $cvFilename = trim($_POST['cv_filename'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($vacancyId > 0 && !empty($name) && !empty($email)) {
            $db = Database::getInstance();
            $stmt = $db->query("
                INSERT INTO " . DB_PREFIX . "vacancy_applications
                    (vacancy_id, name, email, phone, motivation, cv_filename, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$vacancyId, $name, $email, $phone, $motivation, $cvFilename, $ip]);
            flash('success', 'Jouw sollicitatie is succesvol ontvangen. Wij nemen zo snel mogelijk contact op.');
        } else {
            flash('error', 'Vul alle verplichte velden in.');
        }

        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }
});
