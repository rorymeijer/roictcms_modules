<?php
/**
 * Two-Factor Authentication – Boot-bestand
 * Geladen door ModuleManager::bootModules() bij elke actieve pagina-aanvraag.
 */

require_once __DIR__ . '/functions.php';

// ── Sidebar-navigatielink (altijd als eerste registreren) ─────────────────
// Door de hook vóór de interceptor te registreren, verschijnt de link altijd
// in de sidebar – ook als de interceptor later een redirect uitvoert of een
// fout veroorzaakt.
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'two-factor-auth' ? 'active' : '';
    echo '<a href="' . BASE_URL . '/modules/two-factor-auth/admin/" class="nav-link ' . $isActive . '">'
       . '<i class="bi bi-shield-lock"></i> Twee-Factor Auth</a>';
});

// ── 2FA-verificatie interceptor ───────────────────────────────────────────
// Als de gebruiker is ingelogd maar 2FA nog niet heeft bevestigd in deze
// sessie, stuur hem door naar de verificatiepagina.
if (Auth::isLoggedIn() && !isset($_SESSION['tfa_verified'])) {
    try {
        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT tfa_enabled, tfa_secret FROM `" . DB_PREFIX . "users` WHERE id = ?",
            [$_SESSION['user_id']]
        );

        if ($user && $user['tfa_enabled'] && !empty($user['tfa_secret'])) {
            // 2FA is ingeschakeld voor deze gebruiker
            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            $verifyUrl  = BASE_URL . '/modules/two-factor-auth/verify.php';

            // Bepaal of we in het admin-gedeelte zijn (en niet al op de verify/logout pagina)
            $adminBase = parse_url(BASE_URL . '/admin/', PHP_URL_PATH) ?? '/admin/';
            $isAdmin   = strpos($currentUri, $adminBase) === 0;
            $isVerify  = strpos($currentUri, '/two-factor-auth/verify.php') !== false;
            $isLogout  = strpos($currentUri, '/logout.php') !== false;

            if ($isAdmin && !$isVerify && !$isLogout) {
                redirect($verifyUrl);
            }
        } else {
            // Gebruiker heeft geen 2FA – markeer als geverifieerd zodat we niet
            // opnieuw de database raadplegen bij elke aanvraag.
            $_SESSION['tfa_verified'] = true;
        }
    } catch (Exception $e) {
        // Kolommen bestaan nog niet (install.php niet uitgevoerd) of andere
        // databasefout – blokkeer de gebruiker niet maar sla de sessie over.
        $_SESSION['tfa_verified'] = true;
    }
}
