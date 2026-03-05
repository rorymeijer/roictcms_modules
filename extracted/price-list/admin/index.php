<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // --- Categories ---
    if ($action === 'add_category') {
        $name       = trim($_POST['name'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        if (empty($name)) {
            $errors[] = 'Categorienaam is verplicht.';
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "price_categories (name, sort_order) VALUES (?, ?)", [$name, $sort_order]);
            flash('success', 'Categorie toegevoegd.');
            redirect(BASE_URL . '/modules/price-list/admin/');
        }
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "price_items WHERE category_id = ?", [$id]);
            $db->query("DELETE FROM " . DB_PREFIX . "price_categories WHERE id = ?", [$id]);
            flash('success', 'Categorie en alle items verwijderd.');
            redirect(BASE_URL . '/modules/price-list/admin/');
        }
    }

    // --- Items ---
    if ($action === 'add_item') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $unit        = trim($_POST['unit'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);

        if ($category_id <= 0 || empty($name)) {
            $errors[] = 'Categorie en naam zijn verplicht.';
        } else {
            $stmt = $db->query("
                INSERT INTO " . DB_PREFIX . "price_items (category_id, name, description, price, unit, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$category_id, $name, $description, $price, $unit, $sort_order]);
            flash('success', 'Item toegevoegd.');
            redirect(BASE_URL . '/modules/price-list/admin/');
        }
    }

    if ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "price_items WHERE id = ?", [$id]);
            flash('success', 'Item verwijderd.');
            redirect(BASE_URL . '/modules/price-list/admin/');
        }
    }

    if ($action === 'toggle_item_status') {
        $id      = (int)($_POST['id'] ?? 0);
        $current = $_POST['current_status'] ?? 'active';
        $new     = ($current === 'active') ? 'inactive' : 'active';
        if ($id > 0) {
            $db->query("UPDATE " . DB_PREFIX . "price_items SET status = ? WHERE id = ?", [$new, $id]);
            flash('success', 'Status bijgewerkt.');
            redirect(BASE_URL . '/modules/price-list/admin/');
        }
    }
}

// Fetch categories
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "price_categories ORDER BY sort_order ASC, id ASC");
$categories = $stmt->fetchAll();

// Fetch items grouped by category
$items = [];
if (!empty($categories)) {
    $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "price_items ORDER BY sort_order ASC, id ASC");
    foreach ($stmt->fetchAll() as $item) {
        $items[$item['category_id']][] = $item;
    }
}

$pageTitle = 'Prijslijst beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-tag me-2"></i>Prijslijst</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-folder-plus me-1"></i>Categorie toevoegen
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="bi bi-plus-lg me-1"></i>Item toevoegen
            </button>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <?php if (empty($categories)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>Maak eerst een categorie aan voordat u items toevoegt.
        </div>
    <?php else: ?>
        <?php foreach ($categories as $cat): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-folder me-2 text-muted"></i><?= e($cat['name']) ?>
                        <small class="text-muted ms-2">Volgorde: <?= (int)$cat['sort_order'] ?></small>
                    </h5>
                    <form method="post" onsubmit="return confirm('Categorie en alle items verwijderen?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Categorie verwijderen
                        </button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Beschrijving</th>
                                <th>Prijs</th>
                                <th>Eenheid</th>
                                <th>Volgorde</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items[$cat['id']])): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">Geen items in deze categorie.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items[$cat['id']] as $item): ?>
                                    <tr>
                                        <td><?= e($item['name']) ?></td>
                                        <td><?= e(mb_substr($item['description'], 0, 60)) ?><?= mb_strlen($item['description']) > 60 ? '…' : '' ?></td>
                                        <td><?= e($item['price']) ?></td>
                                        <td><?= e($item['unit']) ?></td>
                                        <td><?= (int)$item['sort_order'] ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_item_status">
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= e($item['status']) ?>">
                                                <button type="submit" class="badge border-0 <?= $item['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $item['status'] === 'active' ? 'Actief' : 'Inactief' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Item verwijderen?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
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
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_category">
                <div class="modal-header">
                    <h5 class="modal-title">Categorie toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Naam <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Volgorde</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Categorie opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_item">
                <div class="modal-header">
                    <h5 class="modal-title">Item toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Categorie <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">- Selecteer categorie -</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Naam <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschrijving</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prijs</label>
                            <input type="text" name="price" class="form-control" placeholder="bijv. € 99,00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Eenheid</label>
                            <input type="text" name="unit" class="form-control" placeholder="bijv. per uur">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Volgorde</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Item opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
