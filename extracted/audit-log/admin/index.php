<?php
/**
 * Auditlog Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$message = '';
$messageType = 'success';

// Purge oude logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_old'])) {
    csrf_verify();
    $deleted = AuditLog::purgeOld(90);
    AuditLog::log('audit_log.purge', "Verwijderd: {$deleted} logs ouder dan 90 dagen");
    flash('success', $deleted . ' log(s) ouder dan 90 dagen verwijderd.');
    redirect(BASE_URL . '/modules/audit-log/admin/');
}

$userFilter = trim($_GET['user'] ?? '');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 50;

$result = AuditLog::getLogs($page, $perPage, $userFilter);
$logs   = $result['rows'];
$total  = $result['total'];
$pages  = (int) ceil($total / $perPage);

$flashMsg = get_flash();

$pageTitle = 'Auditlog';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-clock-history me-2"></i>Auditlog</h1>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" name="purge_old" value="1"
                class="btn btn-outline-danger btn-sm"
                onclick="return confirm('Alle logs ouder dan 90 dagen verwijderen?')">
            <i class="bi bi-trash me-1"></i>Logs &gt; 90 dagen verwijderen
        </button>
    </form>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter -->
<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="user" class="form-control" placeholder="Filter op gebruiker..."
               value="<?= e($userFilter) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search me-1"></i>Filteren
        </button>
        <?php if ($userFilter): ?>
            <a href="<?= BASE_URL ?>/modules/audit-log/admin/" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i>Wissen
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Logboek</span>
        <small class="text-muted"><?= number_format($total) ?> records<?= $userFilter ? ' (gefilterd)' : '' ?></small>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Datum &amp; tijd</th>
                    <th>Gebruiker</th>
                    <th>Actie</th>
                    <th>Details</th>
                    <th>IP-adres</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Geen logs gevonden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap">
                                <small><?= e(date('d-m-Y H:i:s', strtotime($log['created_at']))) ?></small>
                            </td>
                            <td><?= e($log['user_name']) ?></td>
                            <td><code><?= e($log['action']) ?></code></td>
                            <td>
                                <?php if ($log['details']): ?>
                                    <span class="text-muted small"><?= e(mb_substr($log['details'], 0, 120)) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e($log['ip_address']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginering -->
<?php if ($pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link"
                       href="?page=<?= $i ?><?= $userFilter ? '&user=' . urlencode($userFilter) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
