<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Redirect Manager';
$activePage = 'redirect-manager';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/modules/redirect-manager/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $source = '/' . ltrim(trim($_POST['source'] ?? ''), '/');
        $dest   = trim($_POST['destination'] ?? '');
        $type   = in_array((int)$_POST['type'], [301, 302]) ? (int)$_POST['type'] : 301;
        if ($source !== '/' && $dest) {
            if (!$db->fetch("SELECT id FROM `" . DB_PREFIX . "redirects` WHERE source=?", [$source])) {
                $db->insert(DB_PREFIX . 'redirects', ['source'=>$source,'destination'=>$dest,'type'=>$type,'active'=>1]);
                flash('success', 'Redirect toegevoegd.');
            } else {
                flash('error', 'Er bestaat al een redirect voor deze bron-URL.');
            }
        }
    }
    if ($action === 'toggle') {
        $row = $db->fetch("SELECT active FROM `" . DB_PREFIX . "redirects` WHERE id=?", [(int)$_POST['id']]);
        if ($row) $db->update(DB_PREFIX . 'redirects', ['active' => $row['active'] ? 0 : 1], 'id=?', [(int)$_POST['id']]);
        flash('success', 'Status bijgewerkt.');
    }
    if ($action === 'delete') {
        $db->delete(DB_PREFIX . 'redirects', 'id=?', [(int)$_POST['id']]);
        flash('success', 'Redirect verwijderd.');
    }
    if ($action === 'save_settings') {
        Settings::set('redirects_log_hits', isset($_POST['log_hits']) ? '1' : '0');
        flash('success', 'Instellingen opgeslagen.');
    }
    redirect(BASE_URL . '/admin/modules/redirect-manager/');
}

$redirects = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "redirects` ORDER BY created_at DESC");
require_once ADMIN_PATH . '/includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-arrow-left-right"></i> <?= e($pageTitle) ?></h1></div>
<?= renderFlash() ?>

<div class="card mb-4">
    <div class="card-header"><strong>Redirect toevoegen</strong></div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <label class="form-label">Bron-URL (pad)</label>
                <input type="text" name="source" class="form-control" placeholder="/oude-pagina" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Doel-URL</label>
                <input type="text" name="destination" class="form-control" placeholder="/nieuwe-pagina of https://..." required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="301">301 Permanent</option>
                    <option value="302">302 Tijdelijk</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Toevoegen</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Bron</th><th>Doel</th><th>Type</th><th>Hits</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($redirects as $r): ?>
                <tr>
                    <td><code><?= e($r['source']) ?></code></td>
                    <td class="text-truncate" style="max-width:200px;"><?= e($r['destination']) ?></td>
                    <td><span class="badge <?= $r['type']==301?'bg-primary':'bg-warning text-dark' ?>"><?= (int)$r['type'] ?></span></td>
                    <td><?= number_format((int)$r['hits']) ?></td>
                    <td>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $r['active']?'btn-success':'btn-secondary' ?>">
                                <?= $r['active']?'Actief':'Inactief' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Verwijderen?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($redirects)): ?><tr><td colspan="6" class="text-center text-muted py-4">Nog geen redirects.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Instellingen</strong></div>
    <div class="card-body">
        <form method="POST" class="d-flex align-items-center gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_settings">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="log_hits" id="logHits"
                       <?= Settings::get('redirects_log_hits','1')==='1'?'checked':'' ?>>
                <label class="form-check-label" for="logHits">Hits bijhouden</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button>
        </form>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
