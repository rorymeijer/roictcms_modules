<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title      = trim($_POST['cookie_consent_title'] ?? '');
    $text       = trim($_POST['cookie_consent_text'] ?? '');
    $acceptAll  = trim($_POST['cookie_consent_accept_all'] ?? '');
    $reject     = trim($_POST['cookie_consent_reject'] ?? '');
    $privacyUrl = trim($_POST['cookie_consent_privacy_url'] ?? '');

    if (empty($title)) {
        $errors[] = 'Titel is verplicht.';
    }
    if (empty($text)) {
        $errors[] = 'Tekst is verplicht.';
    }
    if (empty($acceptAll)) {
        $errors[] = 'Label voor "Alles accepteren" is verplicht.';
    }
    if (empty($reject)) {
        $errors[] = 'Label voor "Weigeren" is verplicht.';
    }

    if (empty($errors)) {
        Settings::set('cookie_consent_title', $title);
        Settings::set('cookie_consent_text', $text);
        Settings::set('cookie_consent_accept_all', $acceptAll);
        Settings::set('cookie_consent_reject', $reject);
        Settings::set('cookie_consent_privacy_url', $privacyUrl);
        flash('success', 'Cookie Consent instellingen opgeslagen.');
        redirect(BASE_URL . '/modules/cookie-consent/admin/');
    }
}

$pageTitle = 'Cookie Consent instellingen';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3"><i class="bi bi-shield-check me-2"></i>Cookie Consent</h1>
        <p class="text-muted">Beheer de teksten en labels van de AVG/GDPR-cookiebanner.</p>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <div class="card" style="max-width: 640px;">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">Banner titel <span class="text-danger">*</span></label>
                    <input type="text" name="cookie_consent_title" class="form-control"
                        value="<?= e(Settings::get('cookie_consent_title', 'Cookievoorkeuren')) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Banner tekst <span class="text-danger">*</span></label>
                    <textarea name="cookie_consent_text" class="form-control" rows="3" required><?= e(Settings::get('cookie_consent_text', 'Wij gebruiken cookies voor een optimale werking van de website.')) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Label knop "Alles accepteren" <span class="text-danger">*</span></label>
                    <input type="text" name="cookie_consent_accept_all" class="form-control"
                        value="<?= e(Settings::get('cookie_consent_accept_all', 'Alles accepteren')) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Label knop "Weigeren" <span class="text-danger">*</span></label>
                    <input type="text" name="cookie_consent_reject" class="form-control"
                        value="<?= e(Settings::get('cookie_consent_reject', 'Alleen noodzakelijk')) ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Privacyverklaring URL</label>
                    <input type="url" name="cookie_consent_privacy_url" class="form-control"
                        value="<?= e(Settings::get('cookie_consent_privacy_url', '')) ?>"
                        placeholder="https://example.com/privacy">
                    <div class="form-text">Laat leeg om geen link te tonen.</div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Instellingen opslaan
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
