<?php
/**
 * Meertaligheid Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db  = Database::getInstance();
$tab = $_GET['tab'] ?? 'languages';

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Taal toevoegen
    if (isset($_POST['add_language'])) {
        $name      = trim($_POST['name'] ?? '');
        $code      = trim($_POST['code'] ?? '');
        $flagEmoji = trim($_POST['flag_emoji'] ?? '');

        if ($name && $code) {
            $db->insert(DB_PREFIX . 'languages', [
                'name'       => $name,
                'code'       => strtolower($code),
                'flag_emoji' => $flagEmoji,
                'status'     => 'active',
                'is_default' => 0,
                'sort_order' => 0,
            ]);
            flash('success', 'Taal toegevoegd.');
        } else {
            flash('danger', 'Naam en code zijn verplicht.');
        }
        redirect(BASE_URL . '/modules/multilingual/admin/?tab=languages');
    }

    // Status wijzigen
    if (isset($_POST['toggle_status'])) {
        $langId = (int) $_POST['lang_id'];
        $current = $db->fetchOne("SELECT status FROM " . DB_PREFIX . "languages WHERE id = ?", [$langId]);
        $newStatus = $current === 'active' ? 'inactive' : 'active';
        $db->query("UPDATE " . DB_PREFIX . "languages SET status = ? WHERE id = ?", [$newStatus, $langId]);
        flash('success', 'Status gewijzigd.');
        redirect(BASE_URL . '/modules/multilingual/admin/?tab=languages');
    }

    // Standaard instellen
    if (isset($_POST['set_default'])) {
        $langId   = (int) $_POST['lang_id'];
        $langCode = $db->fetchOne("SELECT code FROM " . DB_PREFIX . "languages WHERE id = ?", [$langId]);
        $db->query("UPDATE " . DB_PREFIX . "languages SET is_default = 0");
        $db->query("UPDATE " . DB_PREFIX . "languages SET is_default = 1 WHERE id = ?", [$langId]);
        $db->query(
            "UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = 'multilingual_default_lang'",
            [$langCode]
        );
        flash('success', 'Standaardtaal ingesteld op ' . $langCode . '.');
        redirect(BASE_URL . '/modules/multilingual/admin/?tab=languages');
    }

    // Vertaling opslaan
    if (isset($_POST['save_translation'])) {
        $langCode    = trim($_POST['lang_code'] ?? '');
        $contentType = trim($_POST['content_type'] ?? '');
        $contentId   = (int) ($_POST['content_id'] ?? 0);
        $fieldName   = trim($_POST['field_name'] ?? '');
        $value       = $_POST['translated_value'] ?? '';

        if ($langCode && $contentType && $contentId && $fieldName) {
            MultilingualModule::setTranslation($contentType, $contentId, $fieldName, $langCode, $value);
            flash('success', 'Vertaling opgeslagen.');
        }
        redirect(BASE_URL . '/modules/multilingual/admin/?tab=translations&lang=' . urlencode($langCode) . '&type=' . urlencode($contentType));
    }
}

// --- Data laden ---
$languages   = MultilingualModule::getLanguages(false);
$flashMsg    = get_flash();

// Vertalingen tab data
$filterLang = $_GET['lang'] ?? '';
$filterType = $_GET['type'] ?? 'page';
$contentItems = [];

if ($tab === 'translations' && $filterLang) {
    if ($filterType === 'page') {
        $contentItems = $db->fetchAll(
            "SELECT id, title FROM " . DB_PREFIX . "pages WHERE status = 'published' ORDER BY title ASC"
        ) ?: [];
    } elseif ($filterType === 'news') {
        $contentItems = $db->fetchAll(
            "SELECT id, title FROM " . DB_PREFIX . "news WHERE status = 'published' ORDER BY title ASC"
        ) ?: [];
    }
}

$pageTitle = 'Meertaligheid';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-translate me-2"></i>Meertaligheid</h1>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabbladen -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'languages' ? 'active' : '' ?>"
           href="?tab=languages">
            <i class="bi bi-flag me-1"></i>Talen
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'translations' ? 'active' : '' ?>"
           href="?tab=translations">
            <i class="bi bi-pencil me-1"></i>Vertalingen
        </a>
    </li>
</ul>

<?php if ($tab === 'languages'): ?>
    <!-- Talen tabblad -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Talen</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vlag</th>
                                <th>Naam</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Standaard</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($languages)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Geen talen gevonden.</td></tr>
                            <?php else: ?>
                                <?php foreach ($languages as $lang): ?>
                                    <tr>
                                        <td><?= e($lang['flag_emoji']) ?></td>
                                        <td><?= e($lang['name']) ?></td>
                                        <td><code><?= e($lang['code']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $lang['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= $lang['status'] === 'active' ? 'Actief' : 'Inactief' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($lang['is_default']): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex gap-1">
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="lang_id" value="<?= (int) $lang['id'] ?>">
                                                <button type="submit" name="toggle_status" value="1"
                                                        class="btn btn-sm btn-outline-secondary">
                                                    <?= $lang['status'] === 'active' ? 'Deactiveren' : 'Activeren' ?>
                                                </button>
                                            </form>
                                            <?php if (!$lang['is_default']): ?>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="lang_id" value="<?= (int) $lang['id'] ?>">
                                                    <button type="submit" name="set_default" value="1"
                                                            class="btn btn-sm btn-outline-warning">
                                                        Standaard
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Taal toevoegen</div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="bijv. Engels" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="bijv. en" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vlag emoji</label>
                            <input type="text" name="flag_emoji" class="form-control" placeholder="bijv. 🇬🇧" maxlength="10">
                        </div>
                        <button type="submit" name="add_language" value="1" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'translations'): ?>
    <!-- Vertalingen tabblad -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Filter</div>
        <div class="card-body">
            <form method="get" class="row g-2">
                <input type="hidden" name="tab" value="translations">
                <div class="col-md-3">
                    <select name="lang" class="form-select">
                        <option value="">-- Kies taal --</option>
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?= e($lang['code']) ?>" <?= $filterLang === $lang['code'] ? 'selected' : '' ?>>
                                <?= e($lang['flag_emoji']) ?> <?= e($lang['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="page" <?= $filterType === 'page' ? 'selected' : '' ?>>Pagina's</option>
                        <option value="news" <?= $filterType === 'news' ? 'selected' : '' ?>>Nieuws</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filteren
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filterLang && !empty($contentItems)): ?>
        <?php foreach ($contentItems as $item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= e($item['title']) ?></div>
                <div class="card-body">
                    <?php
                    $fields = ['title' => 'Titel', 'content' => 'Inhoud'];
                    foreach ($fields as $fieldKey => $fieldLabel):
                        $existing = MultilingualModule::getTranslation($filterType, (int)$item['id'], $fieldKey, $filterLang);
                    ?>
                        <form method="post" class="mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="lang_code" value="<?= e($filterLang) ?>">
                            <input type="hidden" name="content_type" value="<?= e($filterType) ?>">
                            <input type="hidden" name="content_id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="field_name" value="<?= e($fieldKey) ?>">
                            <label class="form-label fw-semibold"><?= e($fieldLabel) ?></label>
                            <?php if ($fieldKey === 'content'): ?>
                                <textarea name="translated_value" class="form-control" rows="4"><?= e($existing ?? '') ?></textarea>
                            <?php else: ?>
                                <input type="text" name="translated_value" class="form-control" value="<?= e($existing ?? '') ?>">
                            <?php endif; ?>
                            <button type="submit" name="save_translation" value="1" class="btn btn-sm btn-success mt-2">
                                <i class="bi bi-save me-1"></i>Opslaan
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($filterLang): ?>
        <div class="alert alert-info">Geen gepubliceerde content gevonden voor dit type.</div>
    <?php else: ?>
        <div class="alert alert-secondary">Selecteer een taal en type om vertalingen te beheren.</div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
