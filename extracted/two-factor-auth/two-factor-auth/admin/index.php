<?php
/**
 * Two-Factor Authentication – Beheerpagina
 * Pad: modules/two-factor-auth/admin/index.php
 * URL: BASE_URL/modules/two-factor-auth/admin/
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireLogin();

require_once dirname(__DIR__) . '/functions.php';

$db         = Database::getInstance();
$pageTitle  = 'Twee-Factor Authenticatie';
$activePage = 'two-factor-auth';

$currentUser = Auth::currentUser();
if (!$currentUser) {
    flash('error', 'Gebruiker niet gevonden.');
    redirect(BASE_URL . '/admin/');
}

// Haal volledige 2FA-gegevens op
$userData = $db->fetch(
    "SELECT tfa_enabled, tfa_secret FROM `" . DB_PREFIX . "users` WHERE id = ?",
    [$currentUser['id']]
);

$tfaEnabled = (bool) ($userData['tfa_enabled'] ?? false);
$tfaSecret  = $userData['tfa_secret'] ?? '';

$action = $_POST['tfa_action'] ?? '';

// ── POST-verwerking ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/two-factor-auth/admin/');
    }

    // Stap 1: Start setup – genereer geheim en sla op in sessie
    if ($action === 'start_setup') {
        $_SESSION['tfa_pending_secret'] = TwoFactorAuth::generateSecret();
        redirect(BASE_URL . '/modules/two-factor-auth/admin/?step=setup');
    }

    // Stap 2: Bevestig setup – verifieer code en sla geheim op in DB
    if ($action === 'confirm_setup') {
        $pendingSecret = $_SESSION['tfa_pending_secret'] ?? '';
        $code          = preg_replace('/\s+/', '', $_POST['tfa_code'] ?? '');

        if (empty($pendingSecret)) {
            flash('error', 'Sessie verlopen. Start de setup opnieuw.');
            redirect(BASE_URL . '/modules/two-factor-auth/admin/');
        }

        if (TwoFactorAuth::verifyCode($pendingSecret, $code)) {
            $db->update(
                DB_PREFIX . 'users',
                ['tfa_enabled' => 1, 'tfa_secret' => $pendingSecret],
                'id = ?',
                [$currentUser['id']]
            );
            unset($_SESSION['tfa_pending_secret']);
            $_SESSION['tfa_verified'] = true; // Huidige sessie is al geverifieerd
            flash('success', 'Twee-factor authenticatie is succesvol ingeschakeld!');
            redirect(BASE_URL . '/modules/two-factor-auth/admin/');
        } else {
            flash('error', 'Ongeldige code. Scan de QR-code opnieuw en probeer het nog een keer.');
            redirect(BASE_URL . '/modules/two-factor-auth/admin/?step=setup');
        }
    }

    // Uitschakelen – verifieer huidig wachtwoord voor beveiliging
    if ($action === 'disable') {
        $password = $_POST['current_password'] ?? '';
        $fullUser = $db->fetch(
            "SELECT password FROM `" . DB_PREFIX . "users` WHERE id = ?",
            [$currentUser['id']]
        );

        if ($fullUser && password_verify($password, $fullUser['password'])) {
            $db->update(
                DB_PREFIX . 'users',
                ['tfa_enabled' => 0, 'tfa_secret' => null],
                'id = ?',
                [$currentUser['id']]
            );
            unset($_SESSION['tfa_pending_secret']);
            flash('success', 'Twee-factor authenticatie is uitgeschakeld.');
            redirect(BASE_URL . '/modules/two-factor-auth/admin/');
        } else {
            flash('error', 'Onjuist wachtwoord. 2FA is niet uitgeschakeld.');
            redirect(BASE_URL . '/modules/two-factor-auth/admin/');
        }
    }

    // Admin: Verplicht 2FA instelling opslaan
    if ($action === 'save_settings' && Auth::isAdmin()) {
        $required = isset($_POST['tfa_required']) ? '1' : '0';
        Settings::set('tfa_required_for_admin', $required);
        flash('success', 'Instellingen opgeslagen.');
        redirect(BASE_URL . '/modules/two-factor-auth/admin/');
    }

    // Admin: Reset 2FA van een gebruiker
    if ($action === 'reset_user' && Auth::isAdmin()) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId && $targetId !== $currentUser['id']) {
            $db->update(
                DB_PREFIX . 'users',
                ['tfa_enabled' => 0, 'tfa_secret' => null],
                'id = ?',
                [$targetId]
            );
            flash('success', '2FA reset voor de geselecteerde gebruiker.');
        }
        redirect(BASE_URL . '/modules/two-factor-auth/admin/');
    }
}

// ── Setup-stap variabelen ─────────────────────────────────────────────────
$step          = $_GET['step'] ?? '';
$pendingSecret = $_SESSION['tfa_pending_secret'] ?? '';
$issuer        = Settings::get('tfa_issuer', Settings::get('site_name', 'ROICT CMS'));
$qrUrl         = '';
$otpUri        = '';

if ($step === 'setup' && !empty($pendingSecret)) {
    $qrUrl  = TwoFactorAuth::getQrCodeUrl($pendingSecret, $currentUser['email'], $issuer);
    $otpUri = TwoFactorAuth::getOtpAuthUri($pendingSecret, $currentUser['email'], $issuer);
}

// Admin: Haal gebruikersoverzicht op
$allUsers = [];
if (Auth::isAdmin()) {
    $allUsers = $db->fetchAll(
        "SELECT id, username, email, role, tfa_enabled FROM `" . DB_PREFIX . "users` ORDER BY username"
    );
}

$tfaRequired = Settings::get('tfa_required_for_admin', '0');

require_once ADMIN_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
    <i class="bi bi-shield-lock me-2" style="color:#2563eb;"></i><?= e($pageTitle) ?>
  </h1>
</div>

<?= renderFlash() ?>

<?php if ($step === 'setup' && !empty($pendingSecret)): ?>
<!-- ── Setup-wizard ─────────────────────────────────────────────────────── -->
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="cms-card">
      <div class="cms-card-header">
        <span class="cms-card-title"><i class="bi bi-qr-code me-2"></i>2FA instellen</span>
      </div>
      <div class="cms-card-body">
        <div class="alert alert-info" style="border-radius:10px;">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Instructies:</strong> Open uw authenticator-app (bijv. Google Authenticator of
          Microsoft Authenticator), tik op <strong>+</strong> en scan de QR-code hieronder.
          Voer daarna de 6-cijferige code in om de setup te bevestigen.
        </div>

        <div class="text-center my-4">
          <img src="<?= e($qrUrl) ?>" alt="QR-code voor 2FA setup"
               width="200" height="200" class="rounded border p-2"
               style="background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08);">
        </div>

        <details class="mb-4">
          <summary class="fw-semibold" style="cursor:pointer;color:#2563eb;font-size:.9rem;">
            <i class="bi bi-key me-1"></i> Handmatig invoeren (geen QR-scanner)
          </summary>
          <div class="mt-3 p-3 rounded" style="background:#f8fafc;border:1.5px solid #e2e8f0;">
            <div class="mb-2" style="font-size:.82rem;color:#64748b;">Geheime sleutel (bewaar veilig):</div>
            <code style="font-size:1.1rem;letter-spacing:.15rem;word-break:break-all;color:#0f172a;">
              <?= e(chunk_split($pendingSecret, 4, ' ')) ?>
            </code>
            <div class="mt-2" style="font-size:.78rem;color:#64748b;">
              Type: TOTP &bull; Periode: 30s &bull; Digits: 6 &bull; Algoritme: SHA1
            </div>
          </div>
        </details>

        <form method="POST" autocomplete="off" id="setupForm">
          <?= csrf_field() ?>
          <input type="hidden" name="tfa_action" value="confirm_setup">
          <div class="mb-3">
            <label class="form-label fw-semibold">Voer de 6-cijferige code in ter bevestiging</label>
            <input
              type="text"
              name="tfa_code"
              class="form-control text-center"
              style="font-size:1.5rem;letter-spacing:.4rem;font-weight:700;"
              maxlength="6"
              pattern="\d{6}"
              inputmode="numeric"
              autocomplete="one-time-code"
              autofocus
              required
              placeholder="000000"
            >
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="bi bi-check-lg me-1"></i> Bevestigen &amp; inschakelen
            </button>
            <a href="<?= BASE_URL ?>/modules/two-factor-auth/admin/" class="btn btn-outline-secondary">
              Annuleren
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelector('[name="tfa_code"]').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        document.getElementById('setupForm').submit();
    }
});
</script>

<?php else: ?>
<!-- ── Statusoverzicht ─────────────────────────────────────────────────── -->
<div class="row g-4">
  <div class="col-md-5">
    <div class="cms-card">
      <div class="cms-card-header">
        <span class="cms-card-title">Mijn 2FA-status</span>
        <?php if ($tfaEnabled): ?>
        <span class="badge-status badge-published">Ingeschakeld</span>
        <?php else: ?>
        <span class="badge-status badge-inactive">Uitgeschakeld</span>
        <?php endif; ?>
      </div>
      <div class="cms-card-body">
        <?php if ($tfaEnabled): ?>
        <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#f0fdf4;border:1.5px solid #bbf7d0;">
          <div style="font-size:2rem;color:#16a34a;"><i class="bi bi-shield-check"></i></div>
          <div>
            <div class="fw-bold" style="color:#15803d;">Twee-factor authenticatie is actief</div>
            <div class="text-muted" style="font-size:.85rem;">
              Uw account is beveiligd met een authenticator-app.
            </div>
          </div>
        </div>
        <p class="text-muted" style="font-size:.88rem;">
          Bij het uitschakelen van 2FA is uw huidige wachtwoord vereist ter beveiliging.
        </p>
        <button class="btn btn-outline-danger w-100" data-bs-toggle="collapse" data-bs-target="#disableForm">
          <i class="bi bi-shield-x me-1"></i> 2FA uitschakelen
        </button>
        <div class="collapse mt-3" id="disableForm">
          <div class="p-3 rounded" style="background:#fff5f5;border:1.5px solid #fecaca;">
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="tfa_action" value="disable">
              <div class="mb-3">
                <label class="form-label fw-semibold">Huidig wachtwoord</label>
                <input type="password" name="current_password" class="form-control" required autofocus>
              </div>
              <button type="submit" class="btn btn-danger w-100">
                <i class="bi bi-trash me-1"></i> Bevestigen – 2FA uitschakelen
              </button>
            </form>
          </div>
        </div>

        <?php else: ?>
        <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#fef9c3;border:1.5px solid #fde68a;">
          <div style="font-size:2rem;color:#d97706;"><i class="bi bi-shield-exclamation"></i></div>
          <div>
            <div class="fw-bold" style="color:#92400e;">Twee-factor authenticatie is niet actief</div>
            <div class="text-muted" style="font-size:.85rem;">
              Schakel 2FA in voor extra beveiliging van uw account.
            </div>
          </div>
        </div>
        <p class="text-muted" style="font-size:.88rem;">
          U heeft een authenticator-app nodig, zoals Google Authenticator of Microsoft Authenticator.
        </p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="tfa_action" value="start_setup">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-plus me-1"></i> 2FA instellen
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="cms-card mb-4">
      <div class="cms-card-header">
        <span class="cms-card-title"><i class="bi bi-info-circle me-2"></i>Wat is twee-factor authenticatie?</span>
      </div>
      <div class="cms-card-body">
        <p style="font-size:.9rem;color:#334155;">
          Twee-factor authenticatie (2FA) voegt een extra beveiligingslaag toe aan uw account.
          Naast uw wachtwoord is er een eenmalige code nodig uit een authenticator-app.
          Zelfs als uw wachtwoord wordt gestolen, kan een aanvaller niet inloggen zonder deze code.
        </p>
        <div class="row g-3 mt-1">
          <div class="col-sm-6">
            <div class="d-flex gap-2 align-items-start">
              <i class="bi bi-google" style="color:#2563eb;font-size:1.2rem;margin-top:2px;"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem;">Google Authenticator</div>
                <div class="text-muted" style="font-size:.78rem;">iOS &amp; Android</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="d-flex gap-2 align-items-start">
              <i class="bi bi-microsoft" style="color:#2563eb;font-size:1.2rem;margin-top:2px;"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem;">Microsoft Authenticator</div>
                <div class="text-muted" style="font-size:.78rem;">iOS &amp; Android</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="d-flex gap-2 align-items-start">
              <i class="bi bi-phone" style="color:#2563eb;font-size:1.2rem;margin-top:2px;"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem;">Authy</div>
                <div class="text-muted" style="font-size:.78rem;">iOS, Android &amp; Desktop</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="d-flex gap-2 align-items-start">
              <i class="bi bi-lock" style="color:#2563eb;font-size:1.2rem;margin-top:2px;"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem;">1Password / Bitwarden</div>
                <div class="text-muted" style="font-size:.78rem;">Wachtwoordbeheerders met TOTP</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if (Auth::isAdmin()): ?>
    <!-- Admininstellingen -->
    <div class="cms-card mb-4">
      <div class="cms-card-header">
        <span class="cms-card-title"><i class="bi bi-gear me-2"></i>Beheerinstellingen</span>
      </div>
      <div class="cms-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="tfa_action" value="save_settings">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="tfa_required"
                   id="tfaRequired" <?= $tfaRequired === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="tfaRequired">
              2FA verplicht voor alle beheerders
            </label>
          </div>
          <p class="text-muted" style="font-size:.83rem;margin-top:-.5rem;">
            Als ingeschakeld, kunnen admins en super-admins niet inloggen zonder actieve 2FA.
            Gewone gebruikers worden niet beïnvloed.
          </p>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-check me-1"></i> Opslaan
          </button>
        </form>
      </div>
    </div>

    <!-- Gebruikersoverzicht -->
    <div class="cms-card">
      <div class="cms-card-header">
        <span class="cms-card-title"><i class="bi bi-people me-2"></i>Gebruikers 2FA-status</span>
      </div>
      <div class="cms-card-body p-0">
        <table class="cms-table">
          <thead>
            <tr>
              <th>Gebruiker</th>
              <th>Rol</th>
              <th>2FA</th>
              <th>Actie</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($u['username']) ?></div>
                <div class="text-muted" style="font-size:.78rem;"><?= e($u['email']) ?></div>
              </td>
              <td><span class="badge bg-secondary"><?= ucfirst(e($u['role'])) ?></span></td>
              <td>
                <?php if ($u['tfa_enabled']): ?>
                <span class="badge-status badge-published"><i class="bi bi-shield-check me-1"></i>Actief</span>
                <?php else: ?>
                <span class="badge-status badge-inactive"><i class="bi bi-shield-x me-1"></i>Inactief</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['tfa_enabled'] && $u['id'] !== $currentUser['id']): ?>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('2FA resetten voor <?= e(addslashes($u['username'])) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tfa_action" value="reset_user">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm btn-icon" title="Reset 2FA">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </form>
                <?php elseif ($u['id'] === $currentUser['id']): ?>
                <span class="text-muted" style="font-size:.78rem;">Uzelf</span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
