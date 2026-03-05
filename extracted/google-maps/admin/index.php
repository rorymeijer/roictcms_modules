<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_api_key') {
        $apiKey = trim($_POST['google_maps_api_key'] ?? '');
        Settings::set('google_maps_api_key', $apiKey);
        flash('success', 'API key opgeslagen.');
        redirect(BASE_URL . '/modules/google-maps/admin/');
    }

    if ($action === 'add_location') {
        $title = trim($_POST['title'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $zoomLevel = (int)($_POST['zoom_level'] ?? 14);
        $mapWidth = trim($_POST['map_width'] ?? '100%');
        $mapHeight = (int)($_POST['map_height'] ?? 400);

        if (empty($title)) {
            $errors[] = 'Titel is verplicht.';
        }
        if (empty($address)) {
            $errors[] = 'Adres is verplicht.';
        }
        $zoomLevel = max(1, min(20, $zoomLevel));

        if (empty($errors)) {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "google_maps_locations
                 (title, address, zoom_level, map_width, map_height, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'active', NOW())", [$title, $address, $zoomLevel, $mapWidth, $mapHeight]);
            flash('success', 'Locatie toegevoegd.');
            redirect(BASE_URL . '/modules/google-maps/admin/');
        }
    }

    if ($action === 'delete_location') {
        $id = (int)($_POST['location_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "google_maps_locations WHERE id = ?", [$id]);
            flash('success', 'Locatie verwijderd.');
        }
        redirect(BASE_URL . '/modules/google-maps/admin/');
    }
}

// Fetch data
$apiKey = Settings::get('google_maps_api_key');
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "google_maps_locations ORDER BY id DESC");
$locations = $stmt->fetchAll();

$pageTitle = 'Google Maps';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-geo-alt me-2"></i>Google Maps</h1>
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

    <!-- API Key Settings -->
    <div class="card mb-4">
        <div class="card-header"><strong>Instellingen</strong></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_api_key">
                <div class="mb-3">
                    <label for="google_maps_api_key" class="form-label">Google Maps API Key</label>
                    <input type="text" class="form-control" id="google_maps_api_key"
                           name="google_maps_api_key" value="<?= e($apiKey) ?>"
                           placeholder="Voer uw Google Maps API key in">
                    <div class="form-text">
                        Haal een API key op via <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.
                        Zorg dat de Maps Embed API is ingeschakeld.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Opslaan
                </button>
            </form>
        </div>
    </div>

    <!-- Add Location Form -->
    <div class="card mb-4">
        <div class="card-header"><strong>Locatie toevoegen</strong></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_location">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="title" class="form-label">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= e($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="address" name="address"
                               value="<?= e($_POST['address'] ?? '') ?>"
                               placeholder="bijv. Kalverstraat 1, Amsterdam" required>
                    </div>
                    <div class="col-md-4">
                        <label for="zoom_level" class="form-label">Zoom niveau (1-20)</label>
                        <input type="number" class="form-control" id="zoom_level" name="zoom_level"
                               value="<?= e($_POST['zoom_level'] ?? 14) ?>" min="1" max="20">
                    </div>
                    <div class="col-md-4">
                        <label for="map_width" class="form-label">Breedte</label>
                        <input type="text" class="form-control" id="map_width" name="map_width"
                               value="<?= e($_POST['map_width'] ?? '100%') ?>"
                               placeholder="bijv. 100% of 600px">
                    </div>
                    <div class="col-md-4">
                        <label for="map_height" class="form-label">Hoogte (px)</label>
                        <input type="number" class="form-control" id="map_height" name="map_height"
                               value="<?= e($_POST['map_height'] ?? 400) ?>" min="100">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Locatie toevoegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Locations List -->
    <div class="card">
        <div class="card-header"><strong>Locaties</strong></div>
        <div class="card-body p-0">
            <?php if (empty($locations)): ?>
                <div class="p-4 text-muted">Nog geen locaties aangemaakt.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Titel</th>
                                <th>Adres</th>
                                <th>Zoom</th>
                                <th>Embed code</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td><?= e($loc['id']) ?></td>
                                    <td><?= e($loc['title']) ?></td>
                                    <td><?= e($loc['address']) ?></td>
                                    <td><?= e($loc['zoom_level']) ?></td>
                                    <td>
                                        <code>[google_map id="<?= e($loc['id']) ?>"]</code>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Locatie verwijderen?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_location">
                                            <input type="hidden" name="location_id" value="<?= e($loc['id']) ?>">
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
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
