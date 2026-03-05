<?php
/**
 * Frontend Login Module — Init
 *
 * Geladen door ModuleManager::bootModules() bij elke pagina-aanvraag.
 * Registreert hooks, verwerkt login/logout/registratie en beschermt pagina's.
 */

// ── 1. Verwerk POST-acties vóór elke output ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flAction = $_POST['fl_action'] ?? '';
    if ($flAction === 'fl_login')    FrontendLoginModule::handleLogin();
    if ($flAction === 'fl_logout')   FrontendLoginModule::handleLogout();
    if ($flAction === 'fl_register') FrontendLoginModule::handleRegister();
}

// ── 2. Beveiligingscontrole: is de huidige pagina beschermd? ─────────────
// Alleen op de frontend uitvoeren (admin-paden overslaan)
$_fl_req_path  = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';
$_fl_base_path = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/');
$_fl_is_admin  = str_starts_with($_fl_req_path, $_fl_base_path . '/admin')
               || str_starts_with($_fl_req_path, $_fl_base_path . '/modules');

if (!$_fl_is_admin && !FrontendLoginModule::isLoggedIn()) {
    $loginSlug  = Settings::get('fl_login_page_slug', 'inloggen');
    $loginPath  = rtrim($_fl_base_path . '/' . ltrim($loginSlug, '/'), '/');

    // Niet doorsturen als we al op de login-pagina staan
    if ($_fl_req_path !== $loginPath) {
        if (FrontendLoginModule::isPathProtected($_fl_req_path)) {
            redirect(BASE_URL . '/' . ltrim($loginSlug, '/') . '?fl_redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
    }
}
unset($_fl_req_path, $_fl_base_path, $_fl_is_admin, $loginSlug, $loginPath);

// ── 3. Admin-zijbalk navigatielink ───────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = str_starts_with($activePage ?? '', 'frontend-login') ? 'active' : '';
    echo '<a href="' . BASE_URL . '/modules/frontend-login/admin/" class="nav-link ' . $isActive . '">'
       . '<i class="bi bi-person-lock"></i> Frontend Login</a>';
});

// ── 4. Stijlen en scripts laden in <head> ─────────────────────────────────
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/frontend-login/assets/css/frontend-login.css">' . PHP_EOL;
    echo '<script src="' . BASE_URL . '/modules/frontend-login/assets/js/frontend-login.js" defer></script>' . PHP_EOL;
});

// ══════════════════════════════════════════════════════════════════════════════
// Module klasse
// ══════════════════════════════════════════════════════════════════════════════

class FrontendLoginModule
{
    const SESSION_KEY = 'fl_user';

    // ── Sessie-helpers ────────────────────────────────────────────────────

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['id']);
    }

    public static function currentUser(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    // ── Beschermingscontrole ──────────────────────────────────────────────

    public static function isPathProtected(string $path): bool
    {
        static $rows = null;
        if ($rows === null) {
            $db   = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT `path` FROM `" . DB_PREFIX . "fl_protected` WHERE `active` = 1"
            ) ?: [];
        }

        $path = rtrim($path, '/') ?: '/';
        foreach ($rows as $row) {
            $p = rtrim($row['path'], '/');
            if (str_ends_with($p, '*')) {
                // Wildcard: /nieuws/* matcht /nieuws of /nieuws/artikel-1
                $prefix = rtrim(substr($p, 0, -1), '/');
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return true;
                }
            } else {
                if ($p === $path) {
                    return true;
                }
            }
        }
        return false;
    }

    // ── Inloggen ─────────────────────────────────────────────────────────

    public static function handleLogin(): void
    {
        if (!csrf_verify()) {
            $_SESSION['fl_error'] = 'Beveiligingsfout. Probeer het opnieuw.';
            redirect(self::currentPageUrl());
        }

        $email    = trim($_POST['fl_email']    ?? '');
        $password = $_POST['fl_password']      ?? '';

        if (!$email || !$password) {
            $_SESSION['fl_error'] = 'Vul alle velden in.';
            redirect(self::currentPageUrl());
        }

        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT * FROM `" . DB_PREFIX . "fl_users` WHERE `email` = ? AND `status` = 'active'",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['fl_error'] = 'Onjuist e-mailadres of wachtwoord.';
            redirect(self::currentPageUrl());
        }

        // Login geslaagd — vernieuw sessie-ID
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
        ];

        $db->update(
            DB_PREFIX . 'fl_users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [(int) $user['id']]
        );

        // Doorsturen naar bedoelde pagina (alleen interne paden toestaan)
        $redirect = $_POST['fl_redirect'] ?? '';
        if ($redirect && str_starts_with($redirect, '/')) {
            redirect(BASE_URL . $redirect);
        }
        $default = Settings::get('fl_redirect_after_login', '');
        redirect($default ?: BASE_URL);
    }

    // ── Uitloggen ─────────────────────────────────────────────────────────

    public static function handleLogout(): void
    {
        if (!csrf_verify()) {
            redirect(BASE_URL);
        }
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
        $loginSlug = Settings::get('fl_login_page_slug', 'inloggen');
        redirect(BASE_URL . '/' . ltrim($loginSlug, '/') . '?fl_loggedout=1');
    }

    // ── Registratie ───────────────────────────────────────────────────────

    public static function handleRegister(): void
    {
        if (!csrf_verify()) {
            $_SESSION['fl_error'] = 'Beveiligingsfout. Probeer het opnieuw.';
            redirect(self::currentPageUrl());
        }

        if (!Settings::get('fl_allow_registration', '1')) {
            $_SESSION['fl_error'] = 'Registratie is momenteel niet beschikbaar.';
            redirect(self::currentPageUrl());
        }

        $username  = trim($_POST['fl_username']  ?? '');
        $email     = trim($_POST['fl_email']     ?? '');
        $password  = $_POST['fl_password']       ?? '';
        $password2 = $_POST['fl_password2']      ?? '';

        if (!$username || !$email || !$password || !$password2) {
            $_SESSION['fl_error'] = 'Vul alle verplichte velden in.';
            redirect(self::currentPageUrl() . '?fl_tab=register');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['fl_error'] = 'Vul een geldig e-mailadres in.';
            redirect(self::currentPageUrl() . '?fl_tab=register');
        }
        if (strlen($password) < 8) {
            $_SESSION['fl_error'] = 'Het wachtwoord moet minimaal 8 tekens bevatten.';
            redirect(self::currentPageUrl() . '?fl_tab=register');
        }
        if ($password !== $password2) {
            $_SESSION['fl_error'] = 'De wachtwoorden komen niet overeen.';
            redirect(self::currentPageUrl() . '?fl_tab=register');
        }

        $db = Database::getInstance();
        if ($db->fetch("SELECT id FROM `" . DB_PREFIX . "fl_users` WHERE `email` = ?", [$email])) {
            $_SESSION['fl_error'] = 'Dit e-mailadres is al in gebruik.';
            redirect(self::currentPageUrl() . '?fl_tab=register');
        }

        $status = Settings::get('fl_auto_activate', '1') ? 'active' : 'pending';
        $db->insert(DB_PREFIX . 'fl_users', [
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $_SESSION['fl_success'] = $status === 'active'
            ? 'Account aangemaakt! U kunt nu inloggen.'
            : 'Account aangemaakt. Wacht op activering door een beheerder.';

        redirect(self::currentPageUrl() . '?fl_tab=login');
    }

    // ── Hulpfuncties ──────────────────────────────────────────────────────

    private static function currentPageUrl(): string
    {
        return strtok($_SERVER['REQUEST_URI'], '?');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Frontend shortcode-functies
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Rendert het login-/registratieformulier of een welkomstbericht.
 *
 * Gebruik in paginacontent:  [frontend_login]
 * Gebruik in een template:   echo fl_login_form();
 */
function fl_login_form(array $attrs = []): string
{
    $error     = $_SESSION['fl_error']   ?? null; unset($_SESSION['fl_error']);
    $success   = $_SESSION['fl_success'] ?? null; unset($_SESSION['fl_success']);
    $loggedOut = isset($_GET['fl_loggedout']);
    $redirect  = $_GET['fl_redirect'] ?? '';

    // Al ingelogd: toon welkomstbericht + uitlogknop
    if (FrontendLoginModule::isLoggedIn()) {
        $user = FrontendLoginModule::currentUser();
        ob_start(); ?>
        <div class="fl-wrap fl-logged-in">
            <div class="fl-avatar"><i class="bi bi-person-circle"></i></div>
            <p class="fl-welcome">Welkom, <strong><?= e($user['username']) ?></strong>!</p>
            <form method="POST" class="fl-logout-form">
                <?= csrf_field() ?>
                <input type="hidden" name="fl_action" value="fl_logout">
                <button type="submit" class="fl-btn fl-btn-secondary">
                    <i class="bi bi-box-arrow-left"></i> Uitloggen
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    $allowReg  = (bool) Settings::get('fl_allow_registration', '1');
    $activeTab = ($allowReg && ($_GET['fl_tab'] ?? '') === 'register') ? 'register' : 'login';
    $formAction = strtok($_SERVER['REQUEST_URI'], '?') . '#fl-form';

    ob_start(); ?>
    <div class="fl-wrap" id="fl-form">

        <?php if ($loggedOut): ?>
        <div class="fl-alert fl-alert-info">
            <i class="bi bi-info-circle-fill"></i> U bent uitgelogd.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="fl-alert fl-alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="fl-alert fl-alert-success">
            <i class="bi bi-check-circle-fill"></i> <?= e($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($allowReg): ?>
        <div class="fl-tabs" role="tablist">
            <a href="?fl_tab=login#fl-form"
               class="fl-tab <?= $activeTab === 'login' ? 'active' : '' ?>"
               role="tab">Inloggen</a>
            <a href="?fl_tab=register#fl-form"
               class="fl-tab <?= $activeTab === 'register' ? 'active' : '' ?>"
               role="tab">Registreren</a>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'login'): ?>
        <!-- ── INLOGGEN ──────────────────────────────────────────────── -->
        <form class="fl-form" method="POST" action="<?= e($formAction) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="fl_action"   value="fl_login">
            <input type="hidden" name="fl_redirect" value="<?= e($redirect) ?>">

            <div class="fl-field">
                <label for="fl_email">E-mailadres</label>
                <input type="email" id="fl_email" name="fl_email" required
                       placeholder="u@voorbeeld.nl" autocomplete="email">
            </div>

            <div class="fl-field">
                <label for="fl_password">Wachtwoord</label>
                <div class="fl-pw-wrap">
                    <input type="password" id="fl_password" name="fl_password" required
                           placeholder="••••••••" autocomplete="current-password">
                    <button type="button" class="fl-pw-toggle" aria-label="Wachtwoord tonen/verbergen">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="fl-btn fl-btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Inloggen
            </button>
        </form>

        <?php elseif ($activeTab === 'register'): ?>
        <!-- ── REGISTREREN ───────────────────────────────────────────── -->
        <form class="fl-form" method="POST" action="<?= e($formAction) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="fl_action" value="fl_register">

            <div class="fl-field">
                <label for="fl_username">Gebruikersnaam <span class="fl-req">*</span></label>
                <input type="text" id="fl_username" name="fl_username" required
                       placeholder="Uw gebruikersnaam" maxlength="100" autocomplete="username">
            </div>

            <div class="fl-field">
                <label for="fl_reg_email">E-mailadres <span class="fl-req">*</span></label>
                <input type="email" id="fl_reg_email" name="fl_email" required
                       placeholder="u@voorbeeld.nl" autocomplete="email">
            </div>

            <div class="fl-field">
                <label for="fl_reg_pw">Wachtwoord <span class="fl-req">*</span></label>
                <div class="fl-pw-wrap">
                    <input type="password" id="fl_reg_pw" name="fl_password" required
                           placeholder="Minimaal 8 tekens" autocomplete="new-password">
                    <button type="button" class="fl-pw-toggle" aria-label="Wachtwoord tonen/verbergen">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="fl-field">
                <label for="fl_pw2">Wachtwoord bevestigen <span class="fl-req">*</span></label>
                <input type="password" id="fl_pw2" name="fl_password2" required
                       placeholder="Herhaal uw wachtwoord" autocomplete="new-password">
            </div>

            <button type="submit" class="fl-btn fl-btn-primary">
                <i class="bi bi-person-plus"></i> Account aanmaken
            </button>
        </form>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('frontend_login',      'fl_login_form');
add_shortcode('frontend_login_form', 'fl_login_form');
