<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Backup Manager';
$activePage = 'backup-manager';

$backupDir = BASE_PATH . '/uploads/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    file_put_contents($backupDir . '/.htaccess', "Deny from all\n");
}

if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $backupDir . '/' . $file;
    if (file_exists($path) && preg_match('/^backup_[\w\-]+\.sql$/', $file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/modules/backup-manager/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_backup') {
        $pdo      = $db->getPdo();
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;
        $sql      = "-- ROICT CMS Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $sql   .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";
            $rows   = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $vals  = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                $sql  .= "INSERT INTO `{$table}` VALUES(" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($filepath, $sql);

        $user = Auth::currentUser();
        $db->insert(DB_PREFIX . 'backup_log', [
            'filename'   => $filename,
            'size_bytes' => filesize($filepath),
            'created_by' => $user['id'] ?? 0,
        ]);

        $maxFiles = (int) Settings::get('backup_max_files', '10');
        $allFiles = glob($backupDir . '/backup_*.sql');
        usort($allFiles, fn($a, $b) => filemtime($a) - filemtime($b));
        while (count($allFiles) > $maxFiles) {
            @unlink(array_shift($allFiles));
        }

        flash('success', "Back-up aangemaakt: {$filename}");
        redirect(BASE_URL . '/admin/modules/backup-manager/');
    }

    if ($action === 'delete_backup') {
        $file = basename($_POST['filename'] ?? '');
        $path = $backupDir . '/' . $file;
        if (file_exists($path) && preg_match('/^backup_[\w\-]+\.sql$/', $file)) {
            unlink($path);
            $db->delete(DB_PREFIX . 'backup_log', 'filename = ?', [$file]);
            flash('success', 'Back-up verwijderd.');
        }
        redirect(BASE_URL . '/admin/modules/backup-manager/');
    }

    if ($action === 'save_settings') {
        Settings::set('backup_max_files', max(1, (int)($_POST['max_files'] ?? 10)));
        flash('success', 'Instellingen opgeslagen.');
        redirect(BASE_URL . '/admin/modules/backup-manager/');
    }
}

$backupFiles = glob($backupDir . '/backup_*.sql') ?: [];
usort($backupFiles, fn($a, $b) => filemtime($b) - filemtime($a));

require_once ADMIN_PATH . '/includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="mb-0"><i class="bi bi-database-down"></i> <?= e($pageTitle) ?></h1>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_backup">
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe back-up</button>
    </form>
</div>
<?= renderFlash() ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><strong>Back-ups</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Bestand</th><th>Grootte</th><th>Datum</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($backupFiles as $fp):
                            $fname = basename($fp);
                            $size  = round(filesize($fp) / 1024, 1);
                            $mtime = date('d-m-Y H:i', filemtime($fp));
                        ?>
                        <tr>
                            <td><i class="bi bi-file-earmark-text text-muted"></i> <?= e($fname) ?></td>
                            <td><?= $size ?> KB</td>
                            <td><?= $mtime ?></td>
                            <td class="text-end">
                                <a href="?download=<?= urlencode($fname) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Back-up verwijderen?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="filename" value="<?= e($fname) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($backupFiles)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Geen back-ups gevonden.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Instellingen</strong></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <label class="form-label">Max. back-ups bewaren</label>
                        <input type="number" name="max_files" class="form-control" min="1" max="100"
                               value="<?= (int) Settings::get('backup_max_files', '10') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Opslaan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
