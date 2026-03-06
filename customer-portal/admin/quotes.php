<?php
/**
 * Customer Portal – admin/quotes.php
 * Offertebeheer.
 */

Auth::requireAdmin();

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

// ----------------------------------------------------------------
// Acties
// ----------------------------------------------------------------
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $taxRate    = (float)($settings->get('cp_tax_rate') ?: 21);
    $validDays  = (int)($settings->get('cp_quote_valid_days') ?: 30);
    $validUntil = date('Y-m-d', strtotime("+{$validDays} days"));

    $quoteId = $db->insert("{$p}cp_quotes", [
        'quote_number' => cp_next_quote_number(),
        'customer_id'  => $customerId,
        'title'        => trim($_POST['title'] ?? 'Offerte'),
        'intro'        => trim($_POST['intro']  ?? ''),
        'footer'       => trim($_POST['footer'] ?? ''),
        'tax_rate'     => $taxRate,
        'valid_until'  => $validUntil,
        'status'       => 'concept',
    ]);
    flash('success', 'Offerte aangemaakt.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=quotes&action=edit&id=' . $quoteId);
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

    // Regels verwerken
    $db->query("DELETE FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$id]);
    $descs  = $_POST['item_desc']  ?? [];
    $qtys   = $_POST['item_qty']   ?? [];
    $prices = $_POST['item_price'] ?? [];
    foreach ($descs as $i => $desc) {
        $desc  = trim($desc);
        $qty   = (float)($qtys[$i]   ?? 1);
        $price = (float)($prices[$i] ?? 0);
        if ($desc === '') {
            continue;
        }
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
    redirect(BASE_URL . 'admin/?module=customer-portal&page=quotes&action=edit&id=' . $id);
}

if ($action === 'mark_sent' && isset($_GET['id'])) {
    csrf_verify();
    $db->update("{$p}cp_quotes", ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')], ['id' => (int)$_GET['id']]);
    flash('success', 'Offerte gemarkeerd als verzonden.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=quotes');
}

if ($action === 'to_invoice' && isset($_GET['id'])) {
    csrf_verify();
    $quote = $db->fetch("SELECT * FROM `{$p}cp_quotes` WHERE `id` = ?", [(int)$_GET['id']]);
    if ($quote) {
        $payDays    = (int)($settings->get('cp_payment_days') ?: 14);
        $invoiceId  = $db->insert("{$p}cp_invoices", [
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
        // Kopieer regels
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
        redirect(BASE_URL . 'admin/?module=customer-portal&page=invoices&action=edit&id=' . $invoiceId);
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    csrf_verify();
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$id]);
    $db->delete("{$p}cp_quotes", ['id' => $id]);
    flash('success', 'Offerte verwijderd.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=quotes');
}

// ----------------------------------------------------------------
// Edit modus
// ----------------------------------------------------------------
if ($action === 'edit' && isset($_GET['id'])) {
    $quote     = $db->fetch("SELECT q.*, c.contact_name, c.company_name FROM `{$p}cp_quotes` q JOIN `{$p}cp_customers` c ON c.id = q.customer_id WHERE q.id = ?", [(int)$_GET['id']]);
    $quoteItems = $db->fetchAll("SELECT * FROM `{$p}cp_quote_items` WHERE `quote_id` = ? ORDER BY `sort_order`", [(int)$_GET['id']]);
    if (!$quote) {
        flash('error', 'Offerte niet gevonden.');
        redirect(BASE_URL . 'admin/?module=customer-portal&page=quotes');
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><sl-icon name="file-earmark-text"></sl-icon> Offerte bewerken – <?= e($quote['quote_number']) ?></h1>
        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes" class="btn btn-outline-secondary">
            <sl-icon name="arrow-left"></sl-icon> Terug
        </a>
    </div>

    <?php if ($flash = flash('success')): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_quote">
        <input type="hidden" name="id" value="<?= (int)$quote['id'] ?>">

        <div class="card mb-3">
            <div class="card-header">Offerte gegevens</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Klant</label>
                        <input type="text" class="form-control" value="<?= e($quote['company_name'] ?: $quote['contact_name']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['concept' => 'Concept', 'sent' => 'Verzonden', 'accepted' => 'Geaccepteerd', 'rejected' => 'Afgewezen', 'expired' => 'Verlopen'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $quote['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Titel</label>
                        <input type="text" name="title" class="form-control" value="<?= e($quote['title']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Introductietekst</label>
                        <textarea name="intro" class="form-control" rows="3"><?= e($quote['intro']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Geldig tot</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= e($quote['valid_until']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">BTW %</label>
                        <input type="number" name="tax_rate" class="form-control" value="<?= e($quote['tax_rate']) ?>" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Korting (€)</label>
                        <input type="number" name="discount" class="form-control" value="<?= e($quote['discount']) ?>" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Voetnoot</label>
                        <textarea name="footer" class="form-control" rows="2"><?= e($quote['footer']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regels -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Offerteregels</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                    <sl-icon name="plus-lg"></sl-icon> Regel toevoegen
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Omschrijving</th>
                            <th style="width:100px">Aantal</th>
                            <th style="width:120px">Prijs (€)</th>
                            <th style="width:120px">Totaal (€)</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($quoteItems as $item): ?>
                        <tr class="item-row">
                            <td><input type="text" name="item_desc[]" class="form-control form-control-sm" value="<?= e($item['description']) ?>"></td>
                            <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" value="<?= e($item['quantity']) ?>" step="0.01"></td>
                            <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" value="<?= e($item['unit_price']) ?>" step="0.01"></td>
                            <td><input type="text" class="form-control form-control-sm item-total" value="<?= number_format($item['line_total'], 2, ',', '.') ?>" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><sl-icon name="trash"></sl-icon></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="row justify-content-end">
                    <div class="col-md-4">
                        <table class="table table-sm mb-0">
                            <tr><td>Subtotaal</td><td class="text-end" id="subtotal">€ <?= number_format($quote['subtotal'], 2, ',', '.') ?></td></tr>
                            <tr><td>BTW (<?= e($quote['tax_rate']) ?>%)</td><td class="text-end" id="taxAmount">€ <?= number_format($quote['tax_amount'], 2, ',', '.') ?></td></tr>
                            <tr class="fw-bold"><td>Totaal</td><td class="text-end" id="grandTotal">€ <?= number_format($quote['total'], 2, ',', '.') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes&action=mark_sent&id=<?= $quote['id'] ?>&<?= csrf_token() ?>"
               class="btn btn-outline-info" onclick="return confirm('Offerte als verzonden markeren?')">
                <sl-icon name="send"></sl-icon> Markeer verzonden
            </a>
            <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes&action=to_invoice&id=<?= $quote['id'] ?>&<?= csrf_token() ?>"
               class="btn btn-outline-success" onclick="return confirm('Factuur aanmaken vanuit deze offerte?')">
                <sl-icon name="receipt"></sl-icon> Naar factuur
            </a>
        </div>
    </form>

    <script>
    document.getElementById('addItemBtn').addEventListener('click', function() {
        const row = document.createElement('tr');
        row.className = 'item-row';
        row.innerHTML = `
            <td><input type="text" name="item_desc[]" class="form-control form-control-sm"></td>
            <td><input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" value="1" step="0.01"></td>
            <td><input type="number" name="item_price[]" class="form-control form-control-sm item-price" value="0" step="0.01"></td>
            <td><input type="text" class="form-control form-control-sm item-total" value="0,00" readonly></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><sl-icon name="trash"></sl-icon></button></td>
        `;
        document.getElementById('itemsBody').appendChild(row);
        bindRowEvents(row);
    });

    function bindRowEvents(row) {
        row.querySelector('.remove-item').addEventListener('click', () => row.remove());
        const qty = row.querySelector('.item-qty');
        const price = row.querySelector('.item-price');
        const total = row.querySelector('.item-total');
        function calcTotal() {
            const t = parseFloat(qty.value || 0) * parseFloat(price.value || 0);
            total.value = t.toFixed(2).replace('.', ',');
        }
        qty.addEventListener('input', calcTotal);
        price.addEventListener('input', calcTotal);
    }

    document.querySelectorAll('.item-row').forEach(bindRowEvents);
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('tr').remove());
    });
    </script>
    <?php
    return;
}

// ----------------------------------------------------------------
// Overzicht
// ----------------------------------------------------------------
$quotes    = $db->fetchAll(
    "SELECT q.*, c.contact_name, c.company_name
     FROM `{$p}cp_quotes` q
     JOIN `{$p}cp_customers` c ON c.id = q.customer_id
     ORDER BY q.created_at DESC"
);
$customers = $db->fetchAll("SELECT `id`, `contact_name`, `company_name` FROM `{$p}cp_customers` WHERE `status` = 'active' ORDER BY `contact_name`");

$statusLabels = ['concept' => ['Concept', 'secondary'], 'sent' => ['Verzonden', 'info'], 'accepted' => ['Geaccepteerd', 'success'], 'rejected' => ['Afgewezen', 'danger'], 'expired' => ['Verlopen', 'warning']];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><sl-icon name="file-earmark-text"></sl-icon> Offertes</h1>
    <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">
        <sl-icon name="plus-lg"></sl-icon> Nieuwe offerte
    </button>
</div>

<?php if ($flash = flash('success')): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Klant</th>
                    <th>Titel</th>
                    <th>Totaal</th>
                    <th>Status</th>
                    <th>Geldig tot</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($quotes)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Nog geen offertes.</td></tr>
            <?php else: ?>
                <?php foreach ($quotes as $q):
                    [$label, $color] = $statusLabels[$q['status']] ?? ['Onbekend', 'secondary'];
                ?>
                <tr>
                    <td><strong><?= e($q['quote_number']) ?></strong></td>
                    <td><?= e($q['company_name'] ?: $q['contact_name']) ?></td>
                    <td><?= e($q['title']) ?></td>
                    <td>€ <?= number_format($q['total'], 2, ',', '.') ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    <td><?= $q['valid_until'] ? date('d-m-Y', strtotime($q['valid_until'])) : '–' ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes&action=edit&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <sl-icon name="pencil"></sl-icon>
                        </a>
                        <a href="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes&action=delete&id=<?= $q['id'] ?>&<?= csrf_token() ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Offerte verwijderen?')">
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

<!-- Nieuwe offerte modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="width:500px">
        <div class="card-header d-flex justify-content-between">
            <strong>Nieuwe offerte</strong>
            <button type="button" class="btn-close" onclick="document.getElementById('createModal').style.display='none'"></button>
        </div>
        <div class="card-body">
            <form method="post" action="<?= BASE_URL ?>admin/?module=customer-portal&page=quotes">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label">Klant *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">– Selecteer klant –</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['company_name'] ?: $c['contact_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Titel</label>
                    <input type="text" name="title" class="form-control" value="Offerte">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Aanmaken</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
</div>
