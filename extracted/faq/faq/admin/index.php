<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();
require_once dirname(__DIR__) . '/functions.php';

$db        = Database::getInstance();
$pageTitle = 'FAQ';
$activePage = 'faq';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/modules/faq/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name !== '') {
            $db->insert(DB_PREFIX . 'faq_categories', ['name' => $name, 'slug' => slug($name)]);
            flash('success', 'Categorie toegevoegd.');
        }
    }
    if ($action === 'delete_category') {
        $db->delete(DB_PREFIX . 'faq_categories', 'id=?', [(int)$_POST['cat_id']]);
        flash('success', 'Categorie verwijderd.');
    }
    if ($action === 'add_item') {
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer']   ?? '');
        if ($q && $a) {
            $db->insert(DB_PREFIX . 'faq_items', [
                'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
                'question'    => $q,
                'answer'      => $a,
                'status'      => $_POST['status'] ?? 'published',
            ]);
            flash('success', 'Vraag toegevoegd.');
        }
    }
    if ($action === 'delete_item') {
        $db->delete(DB_PREFIX . 'faq_items', 'id=?', [(int)$_POST['item_id']]);
        flash('success', 'Vraag verwijderd.');
    }
    redirect(BASE_URL . '/admin/modules/faq/');
}

$categories = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "faq_categories` ORDER BY sort_order, name");
$items      = $db->fetchAll(
    "SELECT f.*, c.name AS cat_name FROM `" . DB_PREFIX . "faq_items` f
     LEFT JOIN `" . DB_PREFIX . "faq_categories` c ON c.id=f.category_id
     ORDER BY f.sort_order, f.id"
);

require_once ADMIN_PATH . '/includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-question-circle"></i> <?= e($pageTitle) ?></h1></div>
<?= renderFlash() ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><strong>Categorie toevoegen</strong></div>
            <div class="card-body">
                <form method="POST" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_category">
                    <input type="text" name="cat_name" class="form-control" placeholder="Categorienaam" required>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i></button>
                </form>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($categories as $cat): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <?= e($cat['name']) ?>
                        <span class="badge bg-secondary ms-1" title="Categorie-ID (gebruik in shortcode)">#<?= (int)$cat['id'] ?></span>
                    </span>
                    <form method="POST" onsubmit="return confirm('Verwijderen?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <div class="list-group-item text-muted text-center small">Geen categorieën.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Nieuwe vraag</strong></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_item">
                    <div class="mb-2">
                        <select name="category_id" class="form-select">
                            <option value="">— Geen categorie —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2"><input type="text" name="question" class="form-control" placeholder="Vraag" required></div>
                    <div class="mb-2"><textarea name="answer" class="form-control" rows="3" placeholder="Antwoord" required></textarea></div>
                    <div class="mb-2">
                        <select name="status" class="form-select">
                            <option value="published">Gepubliceerd</option>
                            <option value="draft">Concept</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Toevoegen</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Vraag</th><th>Categorie</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['question']) ?></td>
                            <td><?= e($item['cat_name'] ?? '—') ?></td>
                            <td><span class="badge <?= $item['status']==='published'?'bg-success':'bg-secondary' ?>"><?= e($item['status']) ?></span></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Verwijderen?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?><tr><td colspan="4" class="text-center text-muted py-4">Nog geen vragen.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><strong><i class="bi bi-code-slash me-1"></i> Shortcode — FAQ insluiten in pagina's &amp; nieuwsberichten</strong></div>
            <div class="card-body">
                <p class="mb-2 small">Voeg een FAQ-blok in via een shortcode in de inhoud van een pagina of nieuwsbericht:</p>
                <table class="table table-sm table-bordered small mb-3">
                    <thead class="table-light">
                        <tr><th>Shortcode</th><th>Resultaat</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[faq]</code></td>
                            <td>Toont <strong>alle</strong> gepubliceerde vragen</td>
                        </tr>
                        <tr>
                            <td><code>[faq 3]</code></td>
                            <td>Toont alleen vragen uit de categorie met <strong>ID 3</strong></td>
                        </tr>
                    </tbody>
                </table>
                <div class="alert alert-info py-2 mb-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Het <strong>categorie-ID</strong> staat als grijs getal (#…) achter elke categorienaam in de lijst links.
                </div>
                <p class="mb-1 small text-muted">Of gebruik de PHP-helper rechtstreeks in een thema-template:</p>
                <pre class="bg-light p-2 rounded small mb-0"><code>&lt;?php
require_once MODULES_PATH . '/faq/functions.php';
echo faq_render_widget();          // alle vragen
echo faq_render_widget(3);         // alleen categorie 3
?&gt;</code></pre>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
