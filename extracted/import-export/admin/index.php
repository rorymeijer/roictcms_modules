<?php
/**
 * Import / Export Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

// Export via GET (download direct)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    $type = $_GET['export'];
    switch ($type) {
        case 'pages': ImportExportModule::exportPages(); break;
        case 'news':  ImportExportModule::exportNews();  break;
        case 'users': ImportExportModule::exportUsers(); break;
    }
}

$importResult = null;

// Import via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $importType = $_POST['import_type'] ?? '';
    $file       = $_FILES['csv_file'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        flash('danger', 'Geen bestand geüpload.');
        redirect(BASE_URL . '/modules/import-export/admin/');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        flash('danger', 'Alleen CSV-bestanden zijn toegestaan.');
        redirect(BASE_URL . '/modules/import-export/admin/');
    }

    try {
        if ($importType === 'pages') {
            $importResult = ImportExportModule::importPages($file);
        } elseif ($importType === 'news') {
            $importResult = ImportExportModule::importNews($file);
        } else {
            flash('danger', 'Onbekend importtype.');
            redirect(BASE_URL . '/modules/import-export/admin/');
        }
    } catch (Exception $e) {
        flash('danger', 'Importfout: ' . $e->getMessage());
        redirect(BASE_URL . '/modules/import-export/admin/');
    }
}

$flashMsg  = get_flash();
$pageTitle = 'Import / Export';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-arrow-down-up me-2"></i>Import / Export</h1>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($importResult !== null): ?>
    <div class="alert alert-<?= empty($importResult['errors']) ? 'success' : 'warning' ?> alert-dismissible fade show">
        <strong><?= (int) $importResult['imported'] ?> items geïmporteerd</strong>,
        <?= (int) $importResult['skipped'] ?> overgeslagen.
        <?php if (!empty($importResult['errors'])): ?>
            <ul class="mb-0 mt-2">
                <?php foreach ($importResult['errors'] as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Export sectie -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-download me-1"></i>Exporteren
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <p class="text-muted small">Download de content als CSV-bestand. Wachtwoorden worden nooit geëxporteerd.</p>

                <a href="?export=pages" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-2"></i>Pagina's exporteren
                </a>
                <a href="?export=news" class="btn btn-outline-primary">
                    <i class="bi bi-newspaper me-2"></i>Nieuws exporteren
                </a>
                <a href="?export=users" class="btn btn-outline-primary">
                    <i class="bi bi-people me-2"></i>Gebruikers exporteren
                </a>
            </div>
        </div>
    </div>

    <!-- Import sectie -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-upload me-1"></i>Importeren
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Upload een CSV-bestand om content te importeren. Bestaande slugs worden overgeslagen.
                    De eerste rij moet de kolomnamen bevatten.
                </p>

                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="import_type" class="form-select" required>
                            <option value="">-- Kies type --</option>
                            <option value="pages">Pagina's</option>
                            <option value="news">Nieuws</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CSV-bestand <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Importeren
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
