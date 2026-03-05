<?php
/**
 * REST API Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Nieuwe API key genereren
    if (isset($_POST['generate_key'])) {
        $name        = trim($_POST['key_name'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (!$name) {
            flash('danger', 'Naam is verplicht.');
            redirect(BASE_URL . '/modules/rest-api/admin/');
        }

        $apiKey = bin2hex(random_bytes(32)); // 64 hex tekens
        $db->insert(DB_PREFIX . 'api_keys', [
            'name'        => $name,
            'api_key'     => $apiKey,
            'permissions' => json_encode(array_values($permissions)),
            'status'      => 'active',
        ]);
        flash('success', 'API key aangemaakt: <code>' . htmlspecialchars($apiKey, ENT_QUOTES) . '</code>');
        redirect(BASE_URL . '/modules/rest-api/admin/');
    }

    // Status intrekken
    if (isset($_POST['revoke_key'])) {
        $keyId = (int) $_POST['key_id'];
        $db->query("UPDATE " . DB_PREFIX . "api_keys SET status = 'inactive' WHERE id = ?", [$keyId]);
        flash('success', 'API key ingetrokken.');
        redirect(BASE_URL . '/modules/rest-api/admin/');
    }

    // Key verwijderen
    if (isset($_POST['delete_key'])) {
        $keyId = (int) $_POST['key_id'];
        $db->query("DELETE FROM " . DB_PREFIX . "api_keys WHERE id = ?", [$keyId]);
        flash('success', 'API key verwijderd.');
        redirect(BASE_URL . '/modules/rest-api/admin/');
    }
}

$keys     = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "api_keys ORDER BY created_at DESC") ?: [];
$flashMsg = get_flash();
$pageTitle = 'REST API Beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-code-slash me-2"></i>REST API Beheer</h1>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= $flashMsg['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- API Keys lijst -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">API Keys</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Naam</th>
                            <th>Key</th>
                            <th>Rechten</th>
                            <th>Laatste gebruik</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($keys)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Geen API keys gevonden.</td></tr>
                        <?php else: ?>
                            <?php foreach ($keys as $key): ?>
                                <?php
                                $perms = json_decode($key['permissions'] ?? '[]', true) ?: [];
                                $maskedKey = substr($key['api_key'], 0, 8) . '...' . substr($key['api_key'], -4);
                                ?>
                                <tr>
                                    <td><?= e($key['name']) ?></td>
                                    <td>
                                        <code class="small" id="key-<?= (int) $key['id'] ?>"><?= e($maskedKey) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                                onclick="copyKey('<?= e($key['api_key']) ?>')"
                                                title="Kopieer key">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php foreach ($perms as $p): ?>
                                            <span class="badge bg-info text-dark"><?= e($p) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $key['last_used'] ? e(date('d-m-Y H:i', strtotime($key['last_used']))) : '—' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $key['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $key['status'] === 'active' ? 'Actief' : 'Inactief' ?>
                                        </span>
                                    </td>
                                    <td class="d-flex gap-1">
                                        <?php if ($key['status'] === 'active'): ?>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                                                <button type="submit" name="revoke_key" value="1"
                                                        class="btn btn-sm btn-outline-warning"
                                                        onclick="return confirm('Key intrekken?')">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                                            <button type="submit" name="delete_key" value="1"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Key definitief verwijderen?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Nieuwe key genereren -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Nieuwe API key</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Naam <span class="text-danger">*</span></label>
                        <input type="text" name="key_name" class="form-control" placeholder="bijv. Mijn App" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rechten</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                   value="pages_read" id="perm_pages">
                            <label class="form-check-label" for="perm_pages">pages_read</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                   value="news_read" id="perm_news">
                            <label class="form-check-label" for="perm_news">news_read</label>
                        </div>
                    </div>
                    <button type="submit" name="generate_key" value="1" class="btn btn-primary w-100">
                        <i class="bi bi-key me-1"></i>Key genereren
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- API Documentatie -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-book me-1"></i>API Documentatie
    </div>
    <div class="card-body">
        <p>Authenticeer met een <code>Authorization: Bearer {api_key}</code> header of <code>?api_key={key}</code> query parameter.</p>

        <h6 class="mt-3">Endpoints</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Methode</th><th>Endpoint</th><th>Recht</th><th>Beschrijving</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/pages</code></td>
                        <td><code>pages_read</code></td>
                        <td>Lijst van gepubliceerde pagina's</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/pages/{slug}</code></td>
                        <td><code>pages_read</code></td>
                        <td>Enkele pagina op slug</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/news</code></td>
                        <td><code>news_read</code></td>
                        <td>Lijst van gepubliceerde nieuwsberichten</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/news/{slug}</code></td>
                        <td><code>news_read</code></td>
                        <td>Enkel nieuwsbericht op slug</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h6 class="mt-3">Voorbeeld verzoek</h6>
        <pre class="bg-light p-3 rounded"><code>curl -H "Authorization: Bearer {api_key}" \
     <?= e(BASE_URL) ?>/api/v1/pages</code></pre>

        <h6 class="mt-3">Foutcodes</h6>
        <ul>
            <li><code>401</code> – Ongeldige of ontbrekende API key</li>
            <li><code>403</code> – Geen recht voor dit endpoint</li>
            <li><code>404</code> – Endpoint of resource niet gevonden</li>
        </ul>
    </div>
</div>

<script>
function copyKey(key) {
    navigator.clipboard.writeText(key).then(function() {
        alert('API key gekopieerd naar klembord!');
    });
}
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
