<?php
/**
 * Customer Portal – admin/_quotes.php
 * Offertebeheer (sub-pagina, geladen via admin/index.php).
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
    $taxRate    = (float)($settings->get('cp_tax_rate') ?: 21);
    $validDays  = (int)($settings->get('cp_quote_valid_days') ?: 30);

    $quoteId = $db->insert("{$p}cp_quotes", [
        'quote_number' => cp_next_quote_number(),
        'customer_id'  => $customerId,
        'title'        => trim($_POST['title'] ?? 'Offerte'),
        'intro'        => '',
        'footer'       => '',
        'tax_rate'     => $taxRate,
        'valid_until'  => date('Y-m-d', strtotime("+{$validDays} days")),
        'status'       => 'concept',
    ]);
    flash('success', 'Offerte aangemaakt.');
    redirect($base . '?page=quotes&action=edit&id=' . $quoteId);
}

if ($action === 'save_quote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $db->update("{$p}cp_quotes", [
        'title'       => trim($_POST['title']       ?? ''),
        'intro'       => trim($_POST['intro']        ?? ''),
        'footer'      => trim($_POST['footer']       ?? ''),
        'discount'    => (float)($_POST['discount']  ?? 0),
        'tax_rate'    => (float)($_POST['tax_rate']  ?? 21),
        'valid_until' => $_POST['valid_until']       ?? null,
        'status'      => $_POST['status']            ?? 'concept',
    ], ['id' => $id]);

    $db->query("DELETE FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$id]);
    $descs  = $_POST['item_desc']  ?? [];
    $qtys   = $_POST['item_qty']   ?? [];
    $prices = $_POST['item_price'] ?? [];
    foreach ($descs as $i => $desc) {
        $desc  = trim($desc);
        if ($desc === '') continue;
        $qty   = (float)($qtys[$i]   ?? 1);
        $price = (float)($prices[$i] ?? 0);
        $db->insert("{$p}cp_quote_items", [
            'quote_id'    => $id,
            'description' => $desc,
            'quantity'    => $qty,
            'unit_price'  => $price,
            'line_total'  => round($qty * $price, 2),
            'sort_order'  => $i,
        ]);
    }
    cp_recalculate_quote($id);
    flash('success', 'Offerte opgeslagen.');
    redirect($base . '?page=quotes&action=edit&id=' . $id);
}

if ($action === 'mark_sent' && isset($_GET['id'])) {
    csrf_verify();
    $db->update("{$p}cp_quotes", ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')], ['id' => (int)$_GET['id']]);
    flash('success', 'Offerte gemarkeerd als verzonden.');
    redirect($base . '?page=quotes');
}

if ($action === 'to_invoice' && isset($_GET['id'])) {
    csrf_verify();
    $quote = $db->fetch("SELECT * FROM `{$p}cp_quotes` WHERE `id` = ?", [(int)$_GET['id']]);
    if ($quote) {
        $payDays   = (int)($settings->get('cp_payment_days') ?: 14);
        $invoiceId = $db->insert("{$p}cp_invoices", [
            'invoice_number' => cp_next_invoice_number(),
            'quote_id'       => $quote['id'],
            'customer_id'    => $quote['customer_id'],
            'title'          => $quote['title'],
            'intro'          => $quote['intro'],
            'footer'         => $quote['footer'],
            'discount'       => $quote['discount'],
            'tax_rate'       => $quote['tax_rate'],
            'invoice_date'   => date('Y-m-d'),
            'due_date'       => date('Y-m-d', strtotime("+{$payDays} days")),
            'status'         => 'concept',
        ]);
        $items = $db->fetchAll("SELECT * FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$quote['id']]);
        foreach ($items as $item) {
            $db->insert("{$p}cp_invoice_items", [
                'invoice_id'  => $invoiceId,
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'line_total'  => $item['line_total'],
                'sort_order'  => $item['sort_order'],
            ]);
        }
        cp_recalculate_invoice($invoiceId);
        $db->update("{$p}cp_quotes", ['status' => 'accepted'], ['id' => $quote['id']]);
        flash('success', 'Factuur aangemaakt vanuit offerte.');
        redirect($base . '?page=invoices&action=edit&id=' . $invoiceId);
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    csrf_verify();
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$id]);
    $db->delete("{$p}cp_quotes", ['id' => $id]);
    flash('success', 'Offerte verwijderd.');
    redirect($base . '?page=quotes');
}

// ----------------------------------------------------------------
// Edit modus
// ----------------------------------------------------------------
if ($action === 'edit' && isset($_GET['id'])) {
    $quote = $db->fetch(
        "SELECT q.*, c.contact_name, c.company_name
         FROM `{$p}cp_quotes` q JOIN `{$p}cp_customers` c ON c.id = q.customer_id
         WHERE q.id = ?", [(int)$_GET['id']]
    );
    if (!$quote) { flash('error', 'Offerte niet gevonden.'); redirect($base . '?page=quotes'); }
    $quoteItems = $db->fetchAll("SELECT * FROM `{$p}cp_quote_items` WHERE `quote_id` = ? ORDER BY `sort_order`", [(int)$_GET['id']]);
    $statusLabels = ['concept' => 'Concept', 'sent' => 'Verzonden', 'accepted' => 'Geaccepteerd', 'rejected' => 'Afgewezen', 'expired' => 'Verlopen'];
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;"><i class="bi bi-file-earmark-text me-2"></i>Offerte bewerken</h1>
            <p class="text-muted mb-0" style="font-size:.85rem;"><?= e($quote['quote_number']) ?></p>
        </div>
        <a href="<?= $base ?>?page=quotes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Terug</a>
    </div>
    <?= renderFlash() ?>
    <form method="post" action="<?= $base ?>?page=quotes">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_quote">
        <input type="hidden" name="id" value="<?= (int)$quote['id'] ?>">
        <div class="cms-card mb-3">
            <div class="cms-card-header"><span class="cms-card-title">Offerte gegevens</span></div>
            <div class="cms-card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Klant</label><input class="form-control" value="<?= e($quote['company_name'] ?: $quote['contact_name']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><?php foreach ($statusLabels as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $quote['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?></select>
                    </div>
                    <div class="col-12"><label class="form-label">Titel</label><input type="text" name="title" class="form-control" value="<?= e($quote['title']) ?>"></div>
                    <div class="col-12"><label class="form-label">Introductietekst</label><textarea name="intro" class="form-control" rows="3"><?= e($quote['intro']) ?></textarea></div>
                    <div class="col-md-4"><label class="form-label">Geldig tot</label><input type="date" name="valid_until" class="form-control" value="<?= e($quote['valid_until']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">BTW %</label><input type="number" name="tax_rate" class="form-control" value="<?= e($quote['tax_rate']) ?>" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Korting (€)</label><input type="number" name="discount" class="form-control" value="<?= e($quote['discount']) ?>" step="0.01"></div>
                    <div class="col-12"><label class="form-label">Voetnoot</label><textarea name="footer" class="form-control" rows="2"><?= e($quote['footer']) ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="cms-card mb-3">
            <div class="cms-card-header">
                <span class="cms-card-title">Offerteregels</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn"><i class="bi bi-plus-lg me-1"></i> Regel toevoegen</button>
            </div>
            <div class="table-responsive">
                <table class="cms-table" id="itemsTable">
                    <thead><tr><th>Omschrijving</th><th style="width:90px">Aantal</th><th style="width:110px">Prijs (€)</th><th style="width:110px">Totaal (€)</th><th style="width:36px"></th></tr></thead>
                    <tbody id="itemsBody">
                        <?php foreach ($quoteItems as $item): ?>
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
                            <tr><td>Subtotaal</td><td class="text-end">€ <?= number_format($quote['subtotal'], 2, ',', '.') ?></td></tr>
                            <tr><td>BTW (<?= e($quote['tax_rate']) ?>%)</td><td class="text-end">€ <?= number_format($quote['tax_amount'], 2, ',', '.') ?></td></tr>
                            <tr class="fw-bold"><td>Totaal</td><td class="text-end">€ <?= number_format($quote['total'], 2, ',', '.') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
            <a href="<?= $base ?>?page=quotes&action=mark_sent&id=<?= $quote['id'] ?>&<?= csrf_token() ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Offerte als verzonden markeren?')"><i class="bi bi-send me-1"></i> Verzonden</a>
            <a href="<?= $base ?>?page=quotes&action=to_invoice&id=<?= $quote['id'] ?>&<?= csrf_token() ?>" class="btn btn-outline-success btn-sm" onclick="return confirm('Factuur aanmaken vanuit offerte?')"><i class="bi bi-receipt me-1"></i> Naar factuur</a>
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
$quotes    = $db->fetchAll("SELECT q.*, c.contact_name, c.company_name FROM `{$p}cp_quotes` q JOIN `{$p}cp_customers` c ON c.id = q.customer_id ORDER BY q.created_at DESC");
$customers = $db->fetchAll("SELECT `id`, `contact_name`, `company_name` FROM `{$p}cp_customers` WHERE `status` = 'active' ORDER BY `contact_name`");
$statusLabels = ['concept' => ['Concept', 'secondary'], 'sent' => ['Verzonden', 'info'], 'accepted' => ['Geaccepteerd', 'success'], 'rejected' => ['Afgewezen', 'danger'], 'expired' => ['Verlopen', 'warning']];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 style="font-size:1.4rem;font-weight:800;margin:0;"><i class="bi bi-file-earmark-text me-2"></i>Offertes</h1></div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').style.display='flex'"><i class="bi bi-plus-lg me-1"></i> Nieuwe offerte</button>
</div>
<?= renderFlash() ?>
<div class="cms-card">
    <table class="cms-table">
        <thead><tr><th>Nummer</th><th>Klant</th><th>Titel</th><th>Totaal</th><th>Status</th><th>Geldig tot</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($quotes)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Nog geen offertes.</td></tr>
        <?php else: foreach ($quotes as $q): [$label, $color] = $statusLabels[$q['status']] ?? ['Onbekend', 'secondary']; ?>
            <tr>
                <td><strong><?= e($q['quote_number']) ?></strong></td>
                <td><?= e($q['company_name'] ?: $q['contact_name']) ?></td>
                <td><?= e($q['title']) ?></td>
                <td>€ <?= number_format($q['total'], 2, ',', '.') ?></td>
                <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                <td class="text-muted"><?= $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '–' ?></td>
                <td class="text-end">
                    <a href="<?= $base ?>?page=quotes&action=edit&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= $base ?>?page=quotes&action=delete&id=<?= $q['id'] ?>&<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Offerte verwijderen?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="cms-card" style="width:480px">
        <div class="cms-card-header"><span class="cms-card-title">Nieuwe offerte</span><button type="button" class="btn-close" onclick="document.getElementById('createModal').style.display='none'"></button></div>
        <div class="cms-card-body">
            <form method="post" action="<?= $base ?>?page=quotes">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3"><label class="form-label">Klant *</label>
                    <select name="customer_id" class="form-select" required><option value="">– Selecteer klant –</option>
                        <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['company_name'] ?: $c['contact_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Titel</label><input type="text" name="title" class="form-control" value="Offerte"></div>
                <div class="d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Aanmaken</button><button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('createModal').style.display='none'">Annuleren</button></div>
            </form>
        </div>
    </div>
</div>
