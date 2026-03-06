<?php
/**
 * Customer Portal – admin/_invoices.php
 * Factuurbeheer (sub-pagina, geladen via admin/index.php).
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$base     = BASE_URL . '/admin/modules/customer-portal/';
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

// ----------------------------------------------------------------
// Acties
// ----------------------------------------------------------------
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $payDays    = (int)($settings->get('cp_payment_days') ?: 14);
    $invoiceId  = $db->insert("{$p}cp_invoices", [
        'invoice_number' => cp_next_invoice_number(),
        'customer_id'    => $customerId,
        'title'          => trim($_POST['title'] ?? 'Factuur'),
        'tax_rate'       => (float)($settings->get('cp_tax_rate') ?: 21),
        'invoice_date'   => date('Y-m-d'),
        'due_date'       => date('Y-m-d', strtotime("+{$payDays} days")),
        'status'         => 'concept',
    ]);
    flash('success', 'Factuur aangemaakt.');
    redirect($base . '?page=invoices&action=edit&id=' . $invoiceId);
}

if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? 'concept';
    $updateData = [
        'title'        => trim($_POST['title']        ?? ''),
        'intro'        => trim($_POST['intro']         ?? ''),
        'footer'       => trim($_POST['footer']        ?? ''),
        'discount'     => (float)($_POST['discount']  ?? 0),
        'tax_rate'     => (float)($_POST['tax_rate']  ?? 21),
        'invoice_date' => $_POST['invoice_date']       ?? date('Y-m-d'),
        'due_date'     => $_POST['due_date']           ?? null,
        'status'       => $newStatus,
    ];
    if ($newStatus === 'paid') {
        $existing = $db->fetch("SELECT `paid_at` FROM `{$p}cp_invoices` WHERE `id` = ?", [$id]);
        if (!$existing['paid_at']) {
            $updateData['paid_at'] = date('Y-m-d H:i:s');
        }
    }
    $db->update("{$p}cp_invoices", $updateData, ['id' => $id]);

    $db->query("DELETE FROM `{$p}cp_invoice_items` WHERE `invoice_id` = ?", [$id]);
    $descs  = $_POST['item_desc']  ?? [];
    $qtys   = $_POST['item_qty']   ?? [];
    $prices = $_POST['item_price'] ?? [];
    foreach ($descs as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty   = (float)($qtys[$i]   ?? 1);
        $price = (float)($prices[$i] ?? 0);
        $db->insert("{$p}cp_invoice_items", [
            'invoice_id'  => $id,
            'description' => $desc,
            'quantity'    => $qty,
            'unit_price'  => $price,
            'line_total'  => round($qty * $price, 2),
            'sort_order'  => $i,
        ]);
    }
    cp_recalculate_invoice($id);
    flash('success', 'Factuur opgeslagen.');
    redirect($base . '?page=invoices&action=edit&id=' . $id);
}

if ($action === 'mark_sent' && isset($_GET['id'])) {
    csrf_verify();
    $db->update("{$p}cp_invoices", ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')], ['id' => (int)$_GET['id']]);
    flash('success', 'Factuur gemarkeerd als verzonden.');
    redirect($base . '?page=invoices');
}

if ($action === 'mark_paid' && isset($_GET['id'])) {
    csrf_verify();
    $db->update("{$p}cp_invoices", ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')], ['id' => (int)$_GET['id']]);
    flash('success', 'Factuur gemarkeerd als betaald.');
    redirect($base . '?page=invoices');
}

if ($action === 'delete' && isset($_GET['id'])) {
    csrf_verify();
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM `{$p}cp_invoice_items` WHERE `invoice_id` = ?", [$id]);
    $db->delete("{$p}cp_invoices", ['id' => $id]);
    flash('success', 'Factuur verwijderd.');
    redirect($base . '?page=invoices');
}

// ----------------------------------------------------------------
// Edit modus
// ----------------------------------------------------------------
if ($action === 'edit' && isset($_GET['id'])) {
    $invoice = $db->fetch(
        "SELECT i.*, c.contact_name, c.company_name
         FROM `{$p}cp_invoices` i JOIN `{$p}cp_customers` c ON c.id = i.customer_id
         WHERE i.id = ?", [(int)$_GET['id']]
    );
    if (!$invoice) { flash('error', 'Factuur niet gevonden.'); redirect($base . '?page=invoices'); }
    $invoiceItems = $db->fetchAll("SELECT * FROM `{$p}cp_invoice_items` WHERE `invoice_id` = ? ORDER BY `sort_order`", [(int)$_GET['id']]);
    $statusLabels = ['concept' => 'Concept', 'sent' => 'Verzonden', 'paid' => 'Betaald', 'overdue' => 'Verlopen', 'cancelled' => 'Geannuleerd'];
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;"><i class="bi bi-receipt me-2"></i>Factuur bewerken</h1>
            <p class="text-muted mb-0" style="font-size:.85rem;"><?= e($invoice['invoice_number']) ?></p>
        </div>
        <a href="<?= $base ?>?page=invoices" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Terug</a>
    </div>
    <?= renderFlash() ?>
    <form method="post" action="<?= $base ?>?page=invoices">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_invoice">
        <input type="hidden" name="id" value="<?= (int)$invoice['id'] ?>">
        <div class="cms-card mb-3">
            <div class="cms-card-header"><span class="cms-card-title">Factuurgegevens</span></div>
            <div class="cms-card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Klant</label><input class="form-control" value="<?= e($invoice['company_name'] ?: $invoice['contact_name']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><?php foreach ($statusLabels as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $invoice['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?></select>
                    </div>
                    <div class="col-12"><label class="form-label">Titel</label><input type="text" name="title" class="form-control" value="<?= e($invoice['title']) ?>"></div>
                    <div class="col-12"><label class="form-label">Introductietekst</label><textarea name="intro" class="form-control" rows="3"><?= e($invoice['intro']) ?></textarea></div>
                    <div class="col-md-4"><label class="form-label">Factuurdatum</label><input type="date" name="invoice_date" class="form-control" value="<?= e($invoice['invoice_date']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Vervaldatum</label><input type="date" name="due_date" class="form-control" value="<?= e($invoice['due_date']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">BTW %</label><input type="number" name="tax_rate" class="form-control" value="<?= e($invoice['tax_rate']) ?>" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Korting (€)</label><input type="number" name="discount" class="form-control" value="<?= e($invoice['discount']) ?>" step="0.01"></div>
                    <div class="col-12"><label class="form-label">Voetnoot</label><textarea name="footer" class="form-control" rows="2"><?= e($invoice['footer']) ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="cms-card mb-3">
            <div class="cms-card-header">
                <span class="cms-card-title">Factuurregels</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn"><i class="bi bi-plus-lg me-1"></i> Regel toevoegen</button>
            </div>
            <div class="table-responsive">
                <table class="cms-table">
                    <thead><tr><th>Omschrijving</th><th style="width:90px">Aantal</th><th style="width:110px">Prijs (€)</th><th style="width:110px">Totaal (€)</th><th style="width:36px"></th></tr></thead>
                    <tbody id="itemsBody">
                        <?php foreach ($invoiceItems as $item): ?>
                        <tr class="item-row">
                            <td><input type="text" name="item_desc[]" class="form-control form-control-sm" value="<?= e($item['description']) ?>"></td>
                            <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" value="<?= e($item['quantity']) ?>" step="0.01"></td>
                            <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" value="<?= e($item['unit_price']) ?>" step="0.01"></td>
                            <td><input type="text" class="form-control form-control-sm item-total" value="<?= number_format($item['line_total'], 2, ',', '.') ?>" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="cms-card-body">
                <div class="row justify-content-end">
                    <div class="col-md-4">
                        <table class="table table-sm mb-0">
                            <tr><td>Subtotaal</td><td class="text-end">€ <?= number_format($invoice['subtotal'], 2, ',', '.') ?></td></tr>
                            <tr><td>BTW (<?= e($invoice['tax_rate']) ?>%)</td><td class="text-end">€ <?= number_format($invoice['tax_amount'], 2, ',', '.') ?></td></tr>
                            <tr class="fw-bold"><td>Totaal</td><td class="text-end">€ <?= number_format($invoice['total'], 2, ',', '.') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
            <a href="<?= $base ?>?page=invoices&action=mark_sent&id=<?= $invoice['id'] ?>&<?= csrf_token() ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Factuur als verzonden markeren?')"><i class="bi bi-send me-1"></i> Verzonden</a>
            <a href="<?= $base ?>?page=invoices&action=mark_paid&id=<?= $invoice['id'] ?>&<?= csrf_token() ?>" class="btn btn-outline-success btn-sm" onclick="return confirm('Factuur als betaald markeren?')"><i class="bi bi-check-circle me-1"></i> Betaald</a>
        </div>
    </form>
    <script>
    document.getElementById('addItemBtn').addEventListener('click', function() {
        const row = document.createElement('tr'); row.className = 'item-row';
        row.innerHTML = `<td><input type="text" name="item_desc[]" class="form-control form-control-sm"></td><td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" value="1" step="0.01"></td><td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" value="0" step="0.01"></td><td><input type="text" class="form-control form-control-sm item-total" value="0,00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('itemsBody').appendChild(row); bindRow(row);
    });
    function bindRow(row) {
        row.querySelector('.remove-item').addEventListener('click', () => row.remove());
        const qty = row.querySelector('.item-qty'), price = row.querySelector('.item-price'), total = row.querySelector('.item-total');
        function calc() { total.value = (parseFloat(qty.value||0)*parseFloat(price.value||0)).toFixed(2).replace('.',','); }
        qty.addEventListener('input', calc); price.addEventListener('input', calc);
    }
    document.querySelectorAll('.item-row').forEach(bindRow);
    </script>
    <?php
    return;
}

// ----------------------------------------------------------------
// Overzicht
// ----------------------------------------------------------------
$invoices  = $db->fetchAll("SELECT i.*, c.contact_name, c.company_name FROM `{$p}cp_invoices` i JOIN `{$p}cp_customers` c ON c.id = i.customer_id ORDER BY i.created_at DESC");
$customers = $db->fetchAll("SELECT `id`, `contact_name`, `company_name` FROM `{$p}cp_customers` WHERE `status` = 'active' ORDER BY `contact_name`");
$openTotal = $db->fetch("SELECT COALESCE(SUM(total),0) total FROM `{$p}cp_invoices` WHERE `status` IN ('sent','overdue')");
$paidTotal = $db->fetch("SELECT COALESCE(SUM(total),0) total FROM `{$p}cp_invoices` WHERE `status` = 'paid'");
$statusLabels = ['concept' => ['Concept', 'secondary'], 'sent' => ['Verzonden', 'info'], 'paid' => ['Betaald', 'success'], 'overdue' => ['Verlopen', 'warning'], 'cancelled' => ['Geannuleerd', 'secondary']];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 style="font-size:1.4rem;font-weight:800;margin:0;"><i class="bi bi-receipt me-2"></i>Facturen</h1></div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').style.display='flex'"><i class="bi bi-plus-lg me-1"></i> Nieuwe factuur</button>
</div>
<?= renderFlash() ?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="cms-card"><div class="cms-card-body text-center">
        <div style="font-size:1.8rem;font-weight:700;color:#d97706;">€ <?= number_format($openTotal['total'], 2, ',', '.') ?></div>
        <div class="text-muted">Openstaand</div>
    </div></div></div>
    <div class="col-md-6"><div class="cms-card"><div class="cms-card-body text-center">
        <div style="font-size:1.8rem;font-weight:700;color:#059669;">€ <?= number_format($paidTotal['total'], 2, ',', '.') ?></div>
        <div class="text-muted">Ontvangen</div>
    </div></div></div>
</div>
<div class="cms-card">
    <table class="cms-table">
        <thead><tr><th>Nummer</th><th>Klant</th><th>Titel</th><th>Totaal</th><th>Status</th><th>Vervaldatum</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($invoices)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Nog geen facturen.</td></tr>
        <?php else: foreach ($invoices as $inv):
            $overdue = $inv['status'] === 'sent' && $inv['due_date'] && $inv['due_date'] < date('Y-m-d');
            [$label, $color] = $statusLabels[$overdue ? 'overdue' : $inv['status']] ?? ['Onbekend', 'secondary']; ?>
            <tr <?= $overdue ? 'style="background:#fffbeb"' : '' ?>>
                <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                <td><?= e($inv['company_name'] ?: $inv['contact_name']) ?></td>
                <td><?= e($inv['title']) ?></td>
                <td>€ <?= number_format($inv['total'], 2, ',', '.') ?></td>
                <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                <td class="text-muted"><?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '–' ?></td>
                <td class="text-end">
                    <a href="<?= $base ?>?page=invoices&action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= $base ?>?page=invoices&action=delete&id=<?= $inv['id'] ?>&<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Factuur verwijderen?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="cms-card" style="width:480px">
        <div class="cms-card-header"><span class="cms-card-title">Nieuwe factuur</span><button type="button" class="btn-close" onclick="document.getElementById('createModal').style.display='none'"></button></div>
        <div class="cms-card-body">
            <form method="post" action="<?= $base ?>?page=invoices">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3"><label class="form-label">Klant *</label>
                    <select name="customer_id" class="form-select" required><option value="">– Selecteer klant –</option>
                        <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['company_name'] ?: $c['contact_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Titel</label><input type="text" name="title" class="form-control" value="Factuur"></div>
                <div class="d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Aanmaken</button><button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('createModal').style.display='none'">Annuleren</button></div>
            </form>
        </div>
    </div>
</div>
