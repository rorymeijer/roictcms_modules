<?php
/**
 * Frontend Login — Instellingen
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Frontend Login — Instellingen';
$activePage = 'frontend-login';

// ── Opslaan ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/settings.php');
    }

    $loginSlug        = trim($_POST['fl_login_page_slug']      ?? 'inloggen');
    $allowReg         = isset($_POST['fl_allow_registration'])  ? '1' : '0';
    $autoActivate     = isset($_POST['fl_auto_activate'])       ? '1' : '0';
    $redirectAfterLogin = trim($_POST['fl_redirect_after_login'] ?? '');

    // Slug saniteren
    $loginSlug = preg_replace('/[^a-z0-9\-\/]/', '', strtolower($loginSlug));
    $loginSlug = trim($loginSlug, '/');
    if (!$loginSlug) $loginSlug = 'inloggen';

    Settings::setMultiple([
        'fl_login_page_slug'      => $loginSlug,
        'fl_allow_registration'   => $allowReg,
        'fl_auto_activate'        => $autoActivate,
        'fl_redirect_after_login' => $redirectAfterLogin,
    ]);

    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . '/modules/frontend-login/admin/settings.php');
}

require_once ADMIN_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
            <i class="bi bi-person-lock me-2" style="color:var(--primary);"></i>Frontend Login
        </h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">Beheer leden en beschermde inhoud</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/modules/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Terug naar modules
    </a>
</div>

<!-- Sub-navigatie -->
<div class="mb-4 d-flex gap-2 flex-wrap">
    <?php
    $subnav = [
        BASE_URL . '/modules/frontend-login/admin/'              => ['Gebruikers',        'people',      false],
        BASE_URL . '/modules/frontend-login/admin/protected.php' => ['Beschermde inhoud', 'shield-lock', false],
        BASE_URL . '/modules/frontend-login/admin/settings.php'  => ['Instellingen',      'gear',        true],
    ];
    foreach ($subnav as $href => [$label, $icon, $isActive]): ?>
    <a href="<?= $href ?>"
       style="display:inline-flex;align-items:center;gap:.45rem;padding:.45rem 1rem;border-radius:8px;text-decoration:none;font-size:.875rem;font-weight:600;border:1.5px solid <?= $isActive ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $isActive ? 'var(--primary)' : 'white' ?>;color:<?= $isActive ? 'white' : 'var(--text-muted)' ?>;">
        <i class="bi bi-<?= $icon ?>"></i> <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?= renderFlash() ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Module-instellingen</span>
            </div>
            <div class="cms-card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <!-- Login-paginaslug -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Inlogpagina-slug</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted" style="font-size:.85rem;"><?= e(BASE_URL) ?>/</span>
                            <input type="text" name="fl_login_page_slug" class="form-control"
                                   value="<?= e(Settings::get('fl_login_page_slug', 'inloggen')) ?>"
                                   placeholder="inloggen" maxlength="100">
                        </div>
                        <div class="form-text">
                            Maak een CMS-pagina aan met deze slug en voeg de shortcode <code>[frontend_login]</code> toe aan de inhoud.
                            Bezoekers worden naar deze pagina gestuurd als ze een beschermde pagina bezoeken.
                        </div>
                    </div>

                    <!-- Doorstuur-URL na login -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Doorsturen na inloggen</label>
                        <input type="url" name="fl_redirect_after_login" class="form-control"
                               value="<?= e(Settings::get('fl_redirect_after_login', '')) ?>"
                               placeholder="<?= e(BASE_URL) ?>">
                        <div class="form-text">
                            URL waar bezoekers naartoe worden gestuurd na een succesvolle login.
                            Laat leeg om naar de homepage te verwijzen. Als een bezoeker een beschermde
                            pagina wilde openen, wordt die pagina altijd als bestemming gebruikt.
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Registratie -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="fl_allow_reg"
                                   name="fl_allow_registration" value="1"
                                   <?= Settings::get('fl_allow_registration', '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="fl_allow_reg">
                                Zelfregistratie toestaan
                            </label>
                        </div>
                        <div class="form-text ms-4">
                            Bezoekers kunnen zichzelf een account aanmaken via het inlogformulier.
                            Schakel dit uit als u leden alleen handmatig wilt toevoegen.
                        </div>
                    </div>

                    <!-- Auto-activering -->
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="fl_auto_activate"
                                   name="fl_auto_activate" value="1"
                                   <?= Settings::get('fl_auto_activate', '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="fl_auto_activate">
                                Nieuwe accounts automatisch activeren
                            </label>
                        </div>
                        <div class="form-text ms-4">
                            Als uitgeschakeld, krijgen nieuwe accounts de status <em>In afwachting</em>
                            en moet u ze handmatig activeren in het gebruikersbeheer.
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Opslaan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Implementatiegids -->
        <div class="cms-card mt-4">
            <div class="cms-card-header">
                <span class="cms-card-title">Snel aan de slag</span>
            </div>
            <div class="cms-card-body">
                <ol style="font-size:.875rem;line-height:1.9;padding-left:1.2rem;margin:0;">
                    <li>Maak een nieuwe CMS-pagina aan met de slug <code><?= e(Settings::get('fl_login_page_slug', 'inloggen')) ?></code>.</li>
                    <li>Voeg de shortcode <code>[frontend_login]</code> toe aan de pagina-inhoud.</li>
                    <li>Ga naar <strong>Beschermde inhoud</strong> en voeg de URL-paden toe die afgeschermd moeten worden.</li>
                    <li>Maak leden aan via <strong>Gebruikers</strong>, of laat bezoekers zichzelf registreren (als ingeschakeld).</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
