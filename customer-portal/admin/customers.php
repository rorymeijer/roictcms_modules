<?php
/**
 * Customer Portal – admin/customers.php
 * Klantenbeheer.
 */

Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;

// ----------------------------------------------------------------
// Acties
// ----------------------------------------------------------------
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $data = [
        'company_name'  => trim($_POST['company_name']  ?? ''),
        'contact_name'  => trim($_POST['contact_name']  ?? ''),
        'email'         => trim($_POST['email']          ?? ''),
        'phone'         => trim($_POST['phone']          ?? ''),
        'address'       => trim($_POST['address']        ?? ''),
        'postcode'      => trim($_POST['postcode']       ?? ''),
        'city'          => trim($_POST['city']           ?? ''),
        'country'       => trim($_POST['country']        ?? 'Nederland'),
        'kvk'           => trim($_POST['kvk']            ?? ''),
        'btw'           => trim($_POST['btw']            ?? ''),
        'notes'         => trim($_POST['notes']          ?? ''),
        'status'        => 'active',
    ];

    if (empty($data['contact_name']) || empty($data['email'])) {
        flash('error', 'Naam en e-mailadres zijn verplicht.');
    } else {
        // Probeer te koppelen aan fl_user
        $flUser = $db->fetch("SELECT `id` FROM `{$p}fl_users` WHERE `email` = ? LIMIT 1", [$data['email']]);
        if ($flUser) {
            $data['fl_user_id'] = $flUser['id'];
        }
        try {
            $db->insert("{$p}cp_customers", $data);
            flash('success', 'Klant aangemaakt.');
        } catch (Exception $e) {
            flash('error', 'E-mailadres is al in gebruik.');
        }
    }
    redirect(BASE_URL . 'admin/?module=customer-portal&page=customers');
}

if ($action === 'toggle' && isset($_GET['id'])) {
    csrf_verify();
    $customer = $db->fetch("SELECT * FROM `{$p}cp_customers` WHERE `id` = ?", [(int)$_GET['id']]);
    if ($customer) {
        $newStatus = $customer['status'] === 'active' ? 'inactive' : 'active';
        $db->update("{$p}cp_customers", ['status' => $newStatus], ['id' => $customer['id']]);
        flash('success', 'Klantstatus bijgewerkt.');
    }
    redirect(BASE_URL . 'admin/?module=customer-portal&page=customers');
}

if ($action === 'delete' && isset($_GET['id'])) {
    csrf_verify();
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM `{$p}cp_quote_items` WHERE `quote_id` IN (SELECT `id` FROM `{$p}cp_quotes` WHERE `customer_id` = ?)", [$id]);
    $db->query("DELETE FROM `{$p}cp_quotes` WHERE `customer_id` = ?", [$id]);
    $db->query("DELETE FROM `{$p}cp_invoice_items` WHERE `invoice_id` IN (SELECT `id` FROM `{$p}cp_invoices` WHERE `customer_id` = ?)", [$id]);
    $db->query("DELETE FROM `{$p}cp_invoices` WHERE `customer_id` = ?", [$id]);
    $db->delete("{$p}cp_customers", ['id' => $id]);
    flash('success', 'Klant verwijderd.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=customers');
}

// ----------------------------------------------------------------
// Klant bewerken
// ----------------------------------------------------------------
if ($action === 'edit_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'company_name'  => trim($_POST['company_name']  ?? ''),
        'contact_name'  => trim($_POST['contact_name']  ?? ''),
        'email'         => trim($_POST['email']          ?? ''),
        'phone'         => trim($_POST['phone']          ?? ''),
        'address'       => trim($_POST['address']        ?? ''),
        'postcode'      => trim($_POST['postcode']       ?? ''),
        'city'          => trim($_POST['city']           ?? ''),
        'country'       => trim($_POST['country']        ?? 'Nederland'),
        'kvk'           => trim($_POST['kvk']            ?? ''),
        'btw'           => trim($_POST['btw']            ?? ''),
        'notes'         => trim($_POST['notes']          ?? ''),
    ];
    $db->update("{$p}cp_customers", $data, ['id' => $id]);
    flash('success', 'Klant opgeslagen.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=customers');
}

// ----------------------------------------------------------------
// Data ophalen
// ----------------------------------------------------------------
$search    = trim($_GET['q'] ?? '');
$customers = $search
    ? $db->fetchAll(
        "SELECT * FROM `{$p}cp_customers`
         WHERE `contact_name` LIKE ? OR `company_name` LIKE ? OR `email` LIKE ?
         ORDER BY `created_at` DESC",
        ["%$search%", "%$search%", "%$search%"]
    )
    : $db->fetchAll("SELECT * FROM `{$p}cp_customers` ORDER BY `created_at` DESC");

$totals = $db->fetch("SELECT COUNT(*) total, SUM(status='active') active, SUM(status='inactive') inactive FROM `{$p}cp_customers`");

$editCustomer = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editCustomer = $db->fetch("SELECT * FROM `{$p}cp_customers` WHERE `id` = ?", [(int)$_GET['id']]);
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><sl-icon name="people"></sl-icon> Klanten</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
        <sl-icon name="plus-lg"></sl-icon> Nieuwe klant
    </button>
</div>

<?php if ($flash = flash('success')): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($flash = flash('error')): ?>
    <div class="alert alert-danger"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Statistieken -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int)$totals['total'] ?></div>
                <div class="text-muted">Totaal klanten</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success"><?= (int)$totals['active'] ?></div>
                <div class="text-muted">Actief</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-secondary"><?= (int)$totals['inactive'] ?></div>
                <div class="text-muted">Inactief</div>
            </div>
        </div>
    </div>
</div>

<!-- Zoekbalk -->
<form method="get" class="mb-3 d-flex gap-2">
    <input type="hidden" name="module" value="customer-portal">
    <input type="hidden" name="page" value="customers">
    <input type="text" name="q" class="form-control" placeholder="Zoek op naam, bedrijf of e-mail…" value="<?= e($search) ?>">
    <button type="submit" class="btn btn-outline-secondary">Zoeken</button>
    <?php if ($search): ?>
        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers" class="btn btn-outline-secondary">Wissen</a>
    <?php endif; ?>
</form>

<!-- Klantentabel -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Bedrijf</th>
                    <th>E-mail</th>
                    <th>Telefoon</th>
                    <th>Status</th>
                    <th>Aangemaakt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Geen klanten gevonden.</td></tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c['contact_name']) ?></td>
                    <td><?= e($c['company_name']) ?></td>
                    <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                    <td><?= e($c['phone']) ?></td>
                    <td>
                        <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= $c['status'] === 'active' ? 'Actief' : 'Inactief' ?>
                        </span>
                    </td>
                    <td><?= date('d-m-Y', strtotime($c['created_at'])) ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers&action=edit&id=<?= $c['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <sl-icon name="pencil"></sl-icon>
                        </a>
                        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers&action=toggle&id=<?= $c['id'] ?>&<?= csrf_token() ?>"
                           class="btn btn-sm btn-outline-secondary"
                           onclick="return confirm('Status wijzigen?')">
                            <sl-icon name="arrow-repeat"></sl-icon>
                        </a>
                        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers&action=delete&id=<?= $c['id'] ?>&<?= csrf_token() ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Klant en alle gerelateerde gegevens permanent verwijderen?')">
                            <sl-icon name="trash"></sl-icon>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Nieuwe klant modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="width:600px;max-height:90vh;overflow-y:auto;">
        <div class="card-header d-flex justify-content-between">
            <strong>Nieuwe klant</strong>
            <button type="button" class="btn-close" onclick="document.getElementById('addModal').style.display='none'"></button>
        </div>
        <div class="card-body">
            <form method="post" action="<?= BASE_URL ?>admin/?module=customer-portal&page=customers">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Contactpersoon *</label>
                        <input type="text" name="contact_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bedrijfsnaam</label>
                        <input type="text" name="company_name" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefoon</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adres</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Postcode</label>
                        <input type="text" name="postcode" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stad</label>
                        <input type="text" name="city" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Land</label>
                        <input type="text" name="country" class="form-control" value="Nederland">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">KVK-nummer</label>
                        <input type="text" name="kvk" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">BTW-nummer</label>
                        <input type="text" name="btw" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notities</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editCustomer): ?>
<!-- Klant bewerken modal (direct geopend via URL) -->
<div id="editModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="width:600px;max-height:90vh;overflow-y:auto;">
        <div class="card-header d-flex justify-content-between">
            <strong>Klant bewerken</strong>
            <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers" class="btn-close"></a>
        </div>
        <div class="card-body">
            <form method="post" action="<?= BASE_URL ?>admin/?module=customer-portal&page=customers">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_save">
                <input type="hidden" name="id" value="<?= (int)$editCustomer['id'] ?>">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Contactpersoon *</label>
                        <input type="text" name="contact_name" class="form-control" value="<?= e($editCustomer['contact_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bedrijfsnaam</label>
                        <input type="text" name="company_name" class="form-control" value="<?= e($editCustomer['company_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($editCustomer['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefoon</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($editCustomer['phone']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adres</label>
                        <input type="text" name="address" class="form-control" value="<?= e($editCustomer['address']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Postcode</label>
                        <input type="text" name="postcode" class="form-control" value="<?= e($editCustomer['postcode']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stad</label>
                        <input type="text" name="city" class="form-control" value="<?= e($editCustomer['city']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Land</label>
                        <input type="text" name="country" class="form-control" value="<?= e($editCustomer['country']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">KVK-nummer</label>
                        <input type="text" name="kvk" class="form-control" value="<?= e($editCustomer['kvk']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">BTW-nummer</label>
                        <input type="text" name="btw" class="form-control" value="<?= e($editCustomer['btw']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notities</label>
                        <textarea name="notes" class="form-control" rows="3"><?= e($editCustomer['notes']) ?></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                    <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=customers" class="btn btn-secondary">Annuleren</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
