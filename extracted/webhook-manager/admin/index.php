<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors  = [];
$view    = $_GET['view'] ?? 'list';
$logWebhookId = isset($_GET['logs']) ? (int)$_GET['logs'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_webhook') {
        $name   = trim($_POST['name'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $event  = trim($_POST['event'] ?? '');
        $secret = trim($_POST['secret'] ?? '');

        if (empty($name)) {
            $errors[] = 'Naam is verplicht.';
        }
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Geldige URL is verplicht.';
        }
        if (!in_array($event, WebhookManager::AVAILABLE_EVENTS, true)) {
            $errors[] = 'Ongeldig event geselecteerd.';
        }

        if (empty($errors)) {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "webhooks
                 (name, url, event, secret, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())", [$name, $url, $event, $secret ?: null]);
            flash('success', 'Webhook toegevoegd.');
            redirect(BASE_URL . '/modules/webhook-manager/admin/');
        }
    }

    if ($action === 'delete_webhook') {
        $id = (int)($_POST['webhook_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "webhooks WHERE id = ?", [$id]);
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "webhook_logs WHERE webhook_id = ?", [$id]);
            flash('success', 'Webhook verwijderd.');
        }
        redirect(BASE_URL . '/modules/webhook-manager/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['webhook_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "webhooks
                 SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?", [$id]);
            flash('success', 'Status gewijzigd.');
        }
        redirect(BASE_URL . '/modules/webhook-manager/admin/');
    }

    if ($action === 'test_webhook') {
        $id = (int)($_POST['webhook_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "webhooks WHERE id = ? LIMIT 1", [$id]);
            $wh = $stmt->fetch();
            if ($wh) {
                $result = WebhookManager::sendTest($wh);
                if ($result['code'] !== null) {
                    flash('success', 'Testbericht verstuurd. Response code: ' . $result['code']);
                } else {
                    flash('warning', 'Testbericht verstuurd maar geen response ontvangen (URL onbereikbaar?).');
                }
            }
        }
        redirect(BASE_URL . '/modules/webhook-manager/admin/');
    }
}

// Fetch data
$webhooks = $db->query("SELECT * FROM " . DB_PREFIX . "webhooks ORDER BY id DESC")->fetchAll();

// Fetch logs if requested
$logs        = [];
$logWebhook  = null;
if ($logWebhookId > 0) {
    $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "webhooks WHERE id = ? LIMIT 1", [$logWebhookId]);
    $logWebhook = $stmt->fetch();

    if ($logWebhook) {
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "webhook_logs WHERE webhook_id = ? ORDER BY sent_at DESC LIMIT 20", [$logWebhookId]);
        $logs = $stmt->fetchAll();
    }
}

$pageTitle = 'Webhook Manager';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-broadcast me-2"></i>Webhook Manager</h1>
        <?php if ($logWebhook): ?>
            <a href="<?= e(BASE_URL . '/modules/webhook-manager/admin/') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Terug naar overzicht
            </a>
        <?php endif; ?>
    </div>

    <?php foreach (get_flash() as $type => $msg): ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($logWebhook): ?>
        <!-- Logs view -->
        <div class="card">
            <div class="card-header">
                <strong>Logs: <?= e($logWebhook['name']) ?></strong>
                <span class="text-muted ms-2">(laatste 20)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="p-4 text-muted">Nog geen logs beschikbaar voor deze webhook.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Event</th>
                                    <th>Response</th>
                                    <th>Verzonden op</th>
                                    <th>Response body</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><code><?= e($log['event']) ?></code></td>
                                        <td>
                                            <?php if ($log['response_code'] !== null): ?>
                                                <?php
                                                $badgeClass = 'bg-success';
                                                if ($log['response_code'] >= 400) {
                                                    $badgeClass = 'bg-danger';
                                                } elseif ($log['response_code'] >= 300) {
                                                    $badgeClass = 'bg-warning text-dark';
                                                }
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= e($log['response_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e(date('d-m-Y H:i:s', strtotime($log['sent_at']))) ?></td>
                                        <td>
                                            <?php if (!empty($log['response_body'])): ?>
                                                <small class="text-muted font-monospace">
                                                    <?= e(mb_strimwidth($log['response_body'], 0, 100, '...')) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Add Webhook Form -->
        <div class="card mb-4">
            <div class="card-header"><strong>Webhook toevoegen</strong></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_webhook">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="name" class="form-label">Naam <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= e($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label for="url" class="form-label">URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="url" name="url"
                                   value="<?= e($_POST['url'] ?? '') ?>"
                                   placeholder="https://example.com/webhook" required>
                        </div>
                        <div class="col-md-4">
                            <label for="event" class="form-label">Event <span class="text-danger">*</span></label>
                            <select class="form-select" id="event" name="event">
                                <?php foreach (WebhookManager::AVAILABLE_EVENTS as $evt): ?>
                                    <option value="<?= e($evt) ?>"
                                        <?= (($_POST['event'] ?? '') === $evt) ? 'selected' : '' ?>>
                                        <?= e($evt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="secret" class="form-label">Secret (optioneel)</label>
                            <input type="text" class="form-control" id="secret" name="secret"
                                   value="<?= e($_POST['secret'] ?? '') ?>"
                                   placeholder="Gebruikt voor HMAC-SHA256 handtekening">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle me-1"></i>Webhook toevoegen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Webhooks List -->
        <div class="card">
            <div class="card-header"><strong>Webhooks</strong></div>
            <div class="card-body p-0">
                <?php if (empty($webhooks)): ?>
                    <div class="p-4 text-muted">Nog geen webhooks aangemaakt.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Naam</th>
                                    <th>URL</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($webhooks as $wh): ?>
                                    <tr>
                                        <td><?= e($wh['name']) ?></td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width:200px;"
                                                  title="<?= e($wh['url']) ?>">
                                                <?= e($wh['url']) ?>
                                            </span>
                                        </td>
                                        <td><code><?= e($wh['event']) ?></code></td>
                                        <td>
                                            <?php if ($wh['status'] === 'active'): ?>
                                                <span class="badge bg-success">Actief</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactief</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex gap-2 flex-wrap">
                                            <!-- Toggle status -->
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="webhook_id" value="<?= e($wh['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                        title="Status wijzigen">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>

                                            <!-- Test -->
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="test_webhook">
                                                <input type="hidden" name="webhook_id" value="<?= e($wh['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                                        title="Testbericht sturen">
                                                    <i class="bi bi-send"></i> Test
                                                </button>
                                            </form>

                                            <!-- Logs -->
                                            <a href="?logs=<?= e($wh['id']) ?>"
                                               class="btn btn-sm btn-outline-info" title="Logs bekijken">
                                                <i class="bi bi-journal-text"></i> Logs
                                            </a>

                                            <!-- Delete -->
                                            <form method="post" class="d-inline"
                                                  onsubmit="return confirm('Webhook verwijderen?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_webhook">
                                                <input type="hidden" name="webhook_id" value="<?= e($wh['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
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
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
