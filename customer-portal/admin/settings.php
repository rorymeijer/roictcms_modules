<?php
/**
 * Customer Portal – admin/settings.php
 * Module-instellingen.
 */

Auth::requireAdmin();

$settings = Settings::getInstance();
$action   = $_POST['action'] ?? '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $keys = [
        'cp_portal_slug', 'cp_company_name', 'cp_company_address',
        'cp_company_postcode', 'cp_company_city', 'cp_company_kvk',
        'cp_company_btw', 'cp_company_iban', 'cp_invoice_prefix',
        'cp_quote_prefix', 'cp_payment_days', 'cp_quote_valid_days',
        'cp_tax_rate', 'cp_portal_enabled',
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $settings->set($key, trim($_POST[$key]));
        }
    }
    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . 'admin/?module=customer-portal&page=settings');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><sl-icon name="gear"></sl-icon> Klantenpaneel – Instellingen</h1>
</div>

<?php if ($flash = flash('success')): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>admin/?module=customer-portal&page=settings">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <!-- Portaal -->
    <div class="card mb-4">
        <div class="card-header">Frontend klantenpaneel</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Portaal URL-slug</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= BASE_URL ?></span>
                        <input type="text" name="cp_portal_slug" class="form-control"
                               value="<?= e($settings->get('cp_portal_slug') ?: 'klanten-portaal') ?>">
                    </div>
                    <div class="form-text">Vereist actieve Frontend Login module.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Klantenpaneel actief</label>
                    <select name="cp_portal_enabled" class="form-select">
                        <option value="1" <?= $settings->get('cp_portal_enabled') === '1' ? 'selected' : '' ?>>Ja</option>
                        <option value="0" <?= $settings->get('cp_portal_enabled') !== '1' ? 'selected' : '' ?>>Nee</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Bedrijfsgegevens -->
    <div class="card mb-4">
        <div class="card-header">Bedrijfsgegevens (op offertes en facturen)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Bedrijfsnaam</label>
                    <input type="text" name="cp_company_name" class="form-control" value="<?= e($settings->get('cp_company_name')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="cp_company_iban" class="form-control" value="<?= e($settings->get('cp_company_iban')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Adres</label>
                    <input type="text" name="cp_company_address" class="form-control" value="<?= e($settings->get('cp_company_address')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Postcode</label>
                    <input type="text" name="cp_company_postcode" class="form-control" value="<?= e($settings->get('cp_company_postcode')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Stad</label>
                    <input type="text" name="cp_company_city" class="form-control" value="<?= e($settings->get('cp_company_city')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">KVK-nummer</label>
                    <input type="text" name="cp_company_kvk" class="form-control" value="<?= e($settings->get('cp_company_kvk')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">BTW-nummer</label>
                    <input type="text" name="cp_company_btw" class="form-control" value="<?= e($settings->get('cp_company_btw')) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Nummering & BTW -->
    <div class="card mb-4">
        <div class="card-header">Nummering & BTW</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Factuurprefix</label>
                    <input type="text" name="cp_invoice_prefix" class="form-control" value="<?= e($settings->get('cp_invoice_prefix') ?: 'F') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Offerteprefix</label>
                    <input type="text" name="cp_quote_prefix" class="form-control" value="<?= e($settings->get('cp_quote_prefix') ?: 'O') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standaard BTW %</label>
                    <input type="number" name="cp_tax_rate" class="form-control" value="<?= e($settings->get('cp_tax_rate') ?: '21') ?>" step="0.01">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Betalingstermijn (dagen)</label>
                    <input type="number" name="cp_payment_days" class="form-control" value="<?= e($settings->get('cp_payment_days') ?: '14') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Offerte geldig (dagen)</label>
                    <input type="number" name="cp_quote_valid_days" class="form-control" value="<?= e($settings->get('cp_quote_valid_days') ?: '30') ?>">
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Opslaan</button>
</form>
