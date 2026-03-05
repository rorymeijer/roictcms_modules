<?php
/**
 * Onderhoudspagina Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fields = [
        'maintenance_page_enabled'    => isset($_POST['enabled']) ? '1' : '0',
        'maintenance_page_title'      => trim($_POST['title'] ?? 'Even geduld...'),
        'maintenance_page_message'    => trim($_POST['message'] ?? ''),
        'maintenance_page_bg_color'   => trim($_POST['bg_color'] ?? '#1a1a2e'),
        'maintenance_page_text_color' => trim($_POST['text_color'] ?? '#ffffff'),
        'maintenance_page_show_email' => isset($_POST['show_email']) ? '1' : '0',
        'maintenance_page_email'      => trim($_POST['email'] ?? ''),
    ];

    foreach ($fields as $key => $value) {
        $ex = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
        if ($ex) {
            $db->query("UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
        } else {
            $db->insert(DB_PREFIX . 'settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . '/modules/maintenance-page/admin/');
}

$getSetting = function (string $key, string $default = '') use ($db): string {
    $val = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
    return ($val !== false && $val !== null) ? (string) $val : $default;
};

$enabled   = $getSetting('maintenance_page_enabled', '0');
$title     = $getSetting('maintenance_page_title', 'Even geduld...');
$message   = $getSetting('maintenance_page_message', 'We zijn druk bezig om de website te verbeteren. Kom snel terug!');
$bgColor   = $getSetting('maintenance_page_bg_color', '#1a1a2e');
$txtColor  = $getSetting('maintenance_page_text_color', '#ffffff');
$showEmail = $getSetting('maintenance_page_show_email', '0');
$email     = $getSetting('maintenance_page_email', '');

$flashMsg  = get_flash();
$pageTitle = 'Onderhoudspagina';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-tools me-2"></i>Onderhoudspagina</h1>
    <a href="<?= e(BASE_URL) ?>/" target="_blank" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-eye me-1"></i>Preview frontend
    </a>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($enabled === '1'): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Onderhoudsmodus is ingeschakeld.</strong>
            Bezoekers zien de onderhoudspagina. U als beheerder kunt de website nog wel gewoon bekijken.
        </div>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Instellingen</div>
                <div class="card-body">

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enabled"
                                   name="enabled" value="1" <?= $enabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enabled">
                                Onderhoudsmodus inschakelen
                            </label>
                        </div>
                        <div class="form-text text-danger">
                            Als u dit aanzet zijn bezoekers niet welkom — u als admin kunt de site nog wel zien.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Titel</label>
                        <input type="text" name="title" class="form-control" value="<?= e($title) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bericht</label>
                        <textarea name="message" class="form-control" rows="4"><?= e($message) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Achtergrondkleur</label>
                            <div class="input-group">
                                <input type="color" name="bg_color" class="form-control form-control-color"
                                       value="<?= e($bgColor) ?>">
                                <input type="text" class="form-control" value="<?= e($bgColor) ?>"
                                       oninput="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tekstkleur</label>
                            <div class="input-group">
                                <input type="color" name="text_color" class="form-control form-control-color"
                                       value="<?= e($txtColor) ?>">
                                <input type="text" class="form-control" value="<?= e($txtColor) ?>"
                                       oninput="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_email"
                                   name="show_email" value="1" <?= $showEmail === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_email">E-mailadres tonen</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-mailadres</label>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>"
                               placeholder="info@voorbeeld.nl">
                    </div>

                </div>
            </div>
        </div>

        <!-- Live preview kaart -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Preview</div>
                <div class="card-body p-0">
                    <div id="preview-box" style="
                        background-color: <?= e($bgColor) ?>;
                        color: <?= e($txtColor) ?>;
                        min-height: 250px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                        padding: 2rem;
                        border-radius: 0 0 0.375rem 0.375rem;
                    ">
                        <div>
                            <div style="font-size:2rem;margin-bottom:.75rem">🔧</div>
                            <h5 id="preview-title"><?= e($title) ?></h5>
                            <p id="preview-message" style="opacity:.8;font-size:.9rem"><?= e($message) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Opslaan
        </button>
    </div>
</form>

<script>
(function () {
    function q(sel) { return document.querySelector(sel); }

    function updatePreview() {
        var box   = document.getElementById('preview-box');
        var title = document.getElementById('preview-title');
        var msg   = document.getElementById('preview-message');

        var bgInputs  = document.querySelectorAll('input[name="bg_color"]');
        var txtInputs = document.querySelectorAll('input[name="text_color"]');

        if (box) {
            box.style.backgroundColor = bgInputs[0] ? bgInputs[0].value : '#1a1a2e';
            box.style.color           = txtInputs[0] ? txtInputs[0].value : '#ffffff';
        }
        if (title && q('input[name="title"]')) title.textContent = q('input[name="title"]').value;
        if (msg   && q('textarea[name="message"]')) msg.textContent = q('textarea[name="message"]').value;
    }

    document.querySelectorAll('input[name="bg_color"], input[name="text_color"], input[name="title"], textarea[name="message"]')
        .forEach(function(el) { el.addEventListener('input', updatePreview); });
})();
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
