<?php
/**
 * Two-Factor Authentication – Verificatiepagina
 * Wordt getoond na een succesvolle wachtwoord-login als 2FA is ingeschakeld.
 */
session_start();
require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Alleen toegankelijk voor ingelogde gebruikers die nog niet 2FA hebben bevestigd
if (!Auth::isLoggedIn()) {
    redirect(BASE_URL . '/admin/login.php');
}

if (isset($_SESSION['tfa_verified'])) {
    redirect(BASE_URL . '/admin/');
}

$db   = Database::getInstance();
$user = $db->fetch(
    "SELECT id, username, email, tfa_enabled, tfa_secret FROM `" . DB_PREFIX . "users` WHERE id = ?",
    [$_SESSION['user_id']]
);

if (!$user || !$user['tfa_enabled'] || empty($user['tfa_secret'])) {
    // 2FA is niet ingeschakeld – markeer als geverifieerd en ga verder
    $_SESSION['tfa_verified'] = true;
    redirect(BASE_URL . '/admin/');
}

require_once __DIR__ . '/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Probeer opnieuw.';
    } else {
        $code = preg_replace('/\s+/', '', $_POST['tfa_code'] ?? '');
        if (TwoFactorAuth::verifyCode($user['tfa_secret'], $code)) {
            $_SESSION['tfa_verified'] = true;
            redirect(BASE_URL . '/admin/');
        } else {
            $error = 'Ongeldige code. Controleer uw authenticator-app en probeer opnieuw.';
        }
    }
}

$siteName = Settings::get('site_name', 'ROICT CMS');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificatie – <?= e($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    font-family: 'Segoe UI', system-ui, sans-serif;
}
.verify-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 30px 80px rgba(0,0,0,.4);
    width: 100%;
    max-width: 420px;
    overflow: hidden;
}
.verify-header {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    padding: 2.5rem;
    color: white;
    text-align: center;
}
.verify-icon {
    width: 64px;
    height: 64px;
    background: rgba(255,255,255,.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1rem;
}
.verify-body { padding: 2.25rem; }
.code-input {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: .5rem;
    text-align: center;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: .75rem;
    width: 100%;
    background: #f8fafc;
    transition: border-color .2s;
}
.code-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    background: white;
}
.btn-verify {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border: none;
    color: white;
    width: 100%;
    padding: .8rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: opacity .2s;
}
.btn-verify:hover { opacity: .9; }
</style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
  <div class="verify-card">

    <div class="verify-header">
      <div class="verify-icon"><i class="bi bi-shield-lock"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Twee-factor verificatie</h1>
      <p style="opacity:.8;margin:.4rem 0 0;font-size:.9rem;">
        Voer de 6-cijferige code in uit uw authenticator-app
      </p>
    </div>

    <div class="verify-body">
      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:10px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" id="tfaForm">
        <?= csrf_field() ?>
        <div class="mb-4">
          <label class="form-label fw-semibold text-center d-block mb-2" style="color:#334155;">Authenticatiecode</label>
          <input
            type="text"
            name="tfa_code"
            class="code-input"
            maxlength="6"
            pattern="\d{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            autofocus
            required
            placeholder="000000"
          >
          <div class="text-muted text-center mt-2" style="font-size:.82rem;">
            <i class="bi bi-clock"></i> De code vernieuwt elke 30 seconden
          </div>
        </div>
        <button type="submit" class="btn-verify">
          <i class="bi bi-check-lg me-1"></i> Verifiëren
        </button>
      </form>

      <div class="text-center mt-4">
        <a href="<?= BASE_URL ?>/admin/logout.php"
           style="color:#64748b;font-size:.85rem;text-decoration:none;">
          <i class="bi bi-box-arrow-left"></i> Uitloggen
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// Automatisch versturen zodra 6 cijfers zijn ingevoerd
document.querySelector('[name="tfa_code"]').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        document.getElementById('tfaForm').submit();
    }
});
</script>
</body>
</html>
