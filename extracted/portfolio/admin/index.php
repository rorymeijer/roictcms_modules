<?php
/**
 * Portfolio Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // --- Items ---
    if ($action === 'add_item') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url   = trim($_POST['image_url'] ?? '');
        $url         = trim($_POST['url'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
        $sort_order  = (int)($_POST['sort_order'] ?? 0);

        if ($title === '') {
            set_flash('error', 'Titel is verplicht.');
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "portfolio_items (title, description, image_url, url, category_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)", [$title, $description, $image_url, $url, $category_id, $sort_order]);
            set_flash('success', 'Portfolio item toegevoegd.');
        }
        redirect(BASE_URL . '/modules/portfolio/admin/?tab=items');
    }

    if ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "portfolio_items WHERE id = ?", [$id]);
            set_flash('success', 'Item verwijderd.');
        }
        redirect(BASE_URL . '/modules/portfolio/admin/?tab=items');
    }

    if ($action === 'toggle_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "portfolio_items SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/portfolio/admin/?tab=items');
    }

    // --- Categorieën ---
    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        $slug = trim($_POST['cat_slug'] ?? '');

        if ($name === '') {
            set_flash('error', 'Naam is verplicht.');
        } else {
            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            }
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "portfolio_categories (name, slug) VALUES (?, ?)", [$name, $slug]);
            set_flash('success', 'Categorie toegevoegd.');
        }
        redirect(BASE_URL . '/modules/portfolio/admin/?tab=categories');
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Ontkoppel items van deze categorie
            $db->query("UPDATE " . DB_PREFIX . "portfolio_items SET category_id = NULL WHERE category_id = ?", [$id]);
            $db->query("DELETE FROM " . DB_PREFIX . "portfolio_categories WHERE id = ?", [$id]);
            set_flash('success', 'Categorie verwijderd.');
        }
        redirect(BASE_URL . '/modules/portfolio/admin/?tab=categories');
    }
}

// Haal data op
$items = $db->fetchAll("
    SELECT i.*, c.name AS category_name
    FROM " . DB_PREFIX . "portfolio_items i
    LEFT JOIN " . DB_PREFIX . "portfolio_categories c ON i.category_id = c.id
    ORDER BY i.sort_order ASC, i.id ASC
");

$categories = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "portfolio_categories ORDER BY name ASC");

$activeTab = $_GET['tab'] ?? 'items';

$pageTitle = 'Portfolio beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-grid me-2"></i>Portfolio</h1>
    </div>

    <?php flash_messages(); ?>

    <!-- Tabbladen -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'items' ? 'active' : '' ?>" href="?tab=items">
                <i class="bi bi-images me-1"></i>Items (<?= count($items) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'categories' ? 'active' : '' ?>" href="?tab=categories">
                <i class="bi bi-tags me-1"></i>Categorieën (<?= count($categories) ?>)
            </a>
        </li>
    </ul>

    <?php if ($activeTab === 'items'): ?>
    <!-- Items tabblad -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Portfolio items</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($items)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen items toegevoegd.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titel</th>
                                        <th>Categorie</th>
                                        <th>Volgorde</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($item['title']) ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                    <br><small class="text-muted"><?= e(mb_substr($item['description'], 0, 50)) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($item['category_name'] ?? '—') ?></td>
                                            <td><?= e($item['sort_order']) ?></td>
                                            <td>
                                                <?php if ($item['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_item">
                                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-toggle-on"></i></button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Item verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><h5 class="card-title mb-0">Item toevoegen</h5></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_item">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Titel <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Omschrijving</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Afbeelding URL</label>
                            <input type="text" name="image_url" class="form-control" placeholder="https://">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Project URL</label>
                            <input type="text" name="url" class="form-control" placeholder="https://">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Categorie</label>
                            <select name="category_id" class="form-select">
                                <option value="">— Geen —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Volgorde</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Item toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Categorieën tabblad -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><h5 class="card-title mb-0">Categorieën</h5></div>
                <div class="card-body p-0">
                    <?php if (empty($categories)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen categorieën aangemaakt.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Naam</th>
                                        <th>Slug</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><strong><?= e($cat['name']) ?></strong></td>
                                            <td><code><?= e($cat['slug']) ?></code></td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Categorie verwijderen? Items worden ontkoppeld.')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><h5 class="card-title mb-0">Categorie toevoegen</h5></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_category">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="cat_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Slug</label>
                            <input type="text" name="cat_slug" class="form-control" placeholder="automatisch-gegenereerd">
                            <div class="form-text">Laat leeg voor automatische generatie.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Categorie toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
