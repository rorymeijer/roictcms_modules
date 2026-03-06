<?php
/**
 * Customer Portal – admin/_customers.php
 * Klantenbeheer (sub-pagina, geladen via admin/index.php).
 */

// Auth en DB zijn al geladen door index.php
$db = Database::getInstance();
$p  = DB_PREFIX;

// Admin URL basis voor deze module
$base = BASE_URL . '/admin/modules/customer-portal/';

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
    redirect($base . '?page=customers');
}

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
    redirect($base . '?page=customers');
}

if ($action === 'toggle' && isset($_GET['id'])) {
    csrf_verify();
    $customer = $db->fetch("SELECT * FROM `{$p}cp_customers` WHERE `id` = ?", [(int)$_GET['id']]);
    if ($customer) {
        $newStatus = $customer['status'] === 'active' ? 'inactive' : 'active';
        $db->update("{$p}cp_customers", ['status' => $newStatus], ['id' => $customer['id']]);
        flash('success', 'Klantstatus bijgewerkt.');
    }
    redirect($base . '?page=customers');
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
    redirect($base . '?page=customers');
}

// ----------------------------------------------------------------
// Data
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
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;"><i class="bi bi-people me-2"></i>Klanten</h1>
        <p class="text-muted mb-0" style="font-size:.85rem;"><?= (int)$totals['total'] ?> klanten in totaal</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'">
        <i class="bi bi-plus-lg me-1"></i> Nieuwe klant
    </button>
</div>

<?= renderFlash() ?>

<!-- Statistieken -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="cms-card"><div class="cms-card-body text-center">
            <div style="font-size:2rem;font-weight:700;"><?= (int)$totals['total'] ?></div>
            <div class="text-muted">Totaal</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="cms-card"><div class="cms-card-body text-center">
            <div style="font-size:2rem;font-weight:700;color:#059669;"><?= (int)$totals['active'] ?></div>
            <div class="text-muted">Actief</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="cms-card"><div class="cms-card-body text-center">
            <div style="font-size:2rem;font-weight:700;color:#6b7280;"><?= (int)$totals['inactive'] ?></div>
            <div class="text-muted">Inactief</div>
        </div></div>
    </div>
</div>

<!-- Zoekbalk -->
<form method="get" action="<?= $base ?>" class="mb-3 d-flex gap-2">
    <input type="hidden" name="page" value="customers">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Zoek op naam, bedrijf of e-mail…" value="<?= e($search) ?>">
    <button type="submit" class="btn btn-outline-secondary btn-sm">Zoeken</button>
    <?php if ($search): ?>
        <a href="<?= $base ?>?page=customers" class="btn btn-outline-secondary btn-sm">Wissen</a>
    <?php endif; ?>
</form>

<!-- Klantentabel -->
<div class="cms-card">
    <table class="cms-table">
        <thead>
            <tr>
                <th>Naam</th><th>Bedrijf</th><th>E-mail</th><th>Telefoon</th><th>Status</th><th>Aangemaakt</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Geen klanten gevonden.</td></tr>
        <?php else: ?>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td class="fw-semibold"><?= e($c['contact_name']) ?></td>
                <td><?= e($c['company_name']) ?></td>
                <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                <td><?= e($c['phone']) ?></td>
                <td><span class="badge-status badge-<?= $c['status'] === 'active' ? 'published' : 'draft' ?>"><?= $c['status'] === 'active' ? 'Actief' : 'Inactief' ?></span></td>
                <td class="text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td class="text-end">
                    <a href="<?= $base ?>?page=customers&action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= $base ?>?page=customers&action=toggle&id=<?= $c['id'] ?>&<?= csrf_token() ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Status wijzigen?')"><i class="bi bi-arrow-repeat"></i></a>
                    <a href="<?= $base ?>?page=customers&action=delete&id=<?= $c['id'] ?>&<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Klant en alle gerelateerde gegevens permanent verwijderen?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Nieuwe klant modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="cms-card" style="width:600px;max-height:90vh;overflow-y:auto;">
        <div class="cms-card-header">
            <span class="cms-card-title">Nieuwe klant</span>
            <button type="button" class="btn-close" onclick="document.getElementById('addModal').style.display='none'"></button>
        </div>
        <div class="cms-card-body">
            <form method="post" action="<?= $base ?>?page=customers">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Contactpersoon *</label><input type="text" name="contact_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Bedrijfsnaam</label><input type="text" name="company_name" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">E-mail *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Telefoon</label><input type="text" name="phone" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Adres</label><input type="text" name="address" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Postcode</label><input type="text" name="postcode" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Stad</label><input type="text" name="city" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Land</label><input type="text" name="country" class="form-control" value="Nederland"></div>
                    <div class="col-md-6"><label class="form-label">KVK</label><input type="text" name="kvk" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">BTW</label><input type="text" name="btw" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addModal').style.display='none'">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editCustomer): ?>
<div id="editModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="cms-card" style="width:600px;max-height:90vh;overflow-y:auto;">
        <div class="cms-card-header">
            <span class="cms-card-title">Klant bewerken</span>
            <a href="<?= $base ?>?page=customers" class="btn-close"></a>
        </div>
        <div class="cms-card-body">
            <form method="post" action="<?= $base ?>?page=customers">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_save">
                <input type="hidden" name="id" value="<?= (int)$editCustomer['id'] ?>">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Contactpersoon *</label><input type="text" name="contact_name" class="form-control" value="<?= e($editCustomer['contact_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Bedrijfsnaam</label><input type="text" name="company_name" class="form-control" value="<?= e($editCustomer['company_name']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">E-mail *</label><input type="email" name="email" class="form-control" value="<?= e($editCustomer['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Telefoon</label><input type="text" name="phone" class="form-control" value="<?= e($editCustomer['phone']) ?>"></div>
                    <div class="col-12"><label class="form-label">Adres</label><input type="text" name="address" class="form-control" value="<?= e($editCustomer['address']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Postcode</label><input type="text" name="postcode" class="form-control" value="<?= e($editCustomer['postcode']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Stad</label><input type="text" name="city" class="form-control" value="<?= e($editCustomer['city']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Land</label><input type="text" name="country" class="form-control" value="<?= e($editCustomer['country']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">KVK</label><input type="text" name="kvk" class="form-control" value="<?= e($editCustomer['kvk']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">BTW</label><input type="text" name="btw" class="form-control" value="<?= e($editCustomer['btw']) ?>"></div>
                    <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" class="form-control" rows="2"><?= e($editCustomer['notes']) ?></textarea></div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
                    <a href="<?= $base ?>?page=customers" class="btn btn-secondary btn-sm">Annuleren</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
