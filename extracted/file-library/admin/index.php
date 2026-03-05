<?php
/**
 * File Library Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Upload directory
$uploadDir = UPLOADS_PATH . '/file-library/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            set_flash('error', 'Titel is verplicht.');
            redirect(BASE_URL . '/modules/file-library/admin/');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'Bestand uploaden mislukt. Controleer het bestand.');
            redirect(BASE_URL . '/modules/file-library/admin/');
        }

        $originalName = basename($_FILES['file']['name']);
        $mimeType     = mime_content_type($_FILES['file']['tmp_name']);
        $fileSize     = (int)$_FILES['file']['size'];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Toegestane bestandstypen
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'png', 'jpg', 'jpeg', 'gif'];
        if (!in_array($ext, $allowed, true)) {
            set_flash('error', 'Bestandstype niet toegestaan.');
            redirect(BASE_URL . '/modules/file-library/admin/');
        }

        $filename = uniqid('fl_', true) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            set_flash('error', 'Bestand kon niet worden opgeslagen.');
            redirect(BASE_URL . '/modules/file-library/admin/');
        }

        $stmt = $db->query("INSERT INTO " . DB_PREFIX . "file_library_files (title, description, filename, original_name, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)", [$title, $description, $filename, $originalName, $fileSize, $mimeType]);
        set_flash('success', 'Bestand succesvol geüpload.');
        redirect(BASE_URL . '/modules/file-library/admin/');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("SELECT filename FROM " . DB_PREFIX . "file_library_files WHERE id = ?", [$id]);
            $row = $stmt->fetch();
            if ($row) {
                $filePath = $uploadDir . $row['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $db->query("DELETE FROM " . DB_PREFIX . "file_library_files WHERE id = ?", [$id]);
                set_flash('success', 'Bestand verwijderd.');
            }
        }
        redirect(BASE_URL . '/modules/file-library/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "file_library_files SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/file-library/admin/');
    }
}

// Haal bestanden op
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "file_library_files ORDER BY created_at DESC");
$files = $stmt->fetchAll();

$pageTitle = 'Bestandsbibliotheek beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-file-earmark-arrow-down me-2"></i>Bestandsbibliotheek</h1>
    </div>

    <?php flash_messages(); ?>

    <div class="row">
        <!-- Lijst van bestanden -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Bestanden (<?= count($files) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($files)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen bestanden geüpload.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titel</th>
                                        <th>Bestand</th>
                                        <th>Grootte</th>
                                        <th>Downloads</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file):
                                        $size = $file['file_size'] >= 1048576
                                            ? round($file['file_size'] / 1048576, 2) . ' MB'
                                            : round($file['file_size'] / 1024, 1) . ' KB';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($file['title']) ?></strong>
                                                <?php if (!empty($file['description'])): ?>
                                                    <br><small class="text-muted"><?= e(mb_substr($file['description'], 0, 50)) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?= e($file['original_name']) ?></small></td>
                                            <td><small><?= e($size) ?></small></td>
                                            <td><span class="badge bg-secondary"><?= (int)$file['download_count'] ?></span></td>
                                            <td>
                                                <?php if ($file['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Bestand verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Verwijderen">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload formulier -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Bestand uploaden</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Titel <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Omschrijving</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bestand <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" required
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.png,.jpg,.jpeg,.gif">
                            <div class="form-text">Toegestaan: PDF, Word, Excel, PPT, TXT, CSV, ZIP, afbeeldingen</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i>Uploaden
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
