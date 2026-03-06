<?php
/**
 * Customer Portal – frontend/profile.php
 * Profielpagina voor de ingelogde klant.
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$customer = cp_get_current_customer();
$user     = FrontendLoginModule::currentUser();
$slug     = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');

$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    csrf_verify();

    $contactName = trim($_POST['contact_name'] ?? '');
    $companyName = trim($_POST['company_name']  ?? '');
    $phone       = trim($_POST['phone']         ?? '');
    $address     = trim($_POST['address']        ?? '');
    $postcode    = trim($_POST['postcode']       ?? '');
    $city        = trim($_POST['city']           ?? '');

    if (empty($contactName)) {
        $errors[] = 'Naam is verplicht.';
    }

    if (empty($errors)) {
        $db->update("{$p}cp_customers", [
            'contact_name' => $contactName,
            'company_name' => $companyName,
            'phone'        => $phone,
            'address'      => $address,
            'postcode'     => $postcode,
            'city'         => $city,
        ], ['id' => $customer['id']]);
        $success = true;
        // Reload customer data
        $customer = cp_get_current_customer();
    }
}

$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Mijn profiel</h1>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link">⊞ Dashboard</a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link">📄 Offertes</a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link">🧾 Facturen</a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link active">👤 Profiel</a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">↩ Uitloggen</a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <div class="cp-card" style="max-width:600px">
            <?php if ($success): ?>
                <div class="cp-alert cp-alert-success">Uw profiel is bijgewerkt.</div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?>
                <div class="cp-alert cp-alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post" action="<?= BASE_URL . $slug ?>/profiel">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="cp-form-group">
                    <label class="cp-label">E-mailadres</label>
                    <input type="email" class="cp-input" value="<?= e($customer['email']) ?>" disabled>
                    <small class="cp-hint">E-mailadres kan niet worden gewijzigd.</small>
                </div>

                <div class="cp-form-group">
                    <label class="cp-label">Contactpersoon *</label>
                    <input type="text" name="contact_name" class="cp-input" value="<?= e($customer['contact_name']) ?>" required>
                </div>

                <div class="cp-form-group">
                    <label class="cp-label">Bedrijfsnaam</label>
                    <input type="text" name="company_name" class="cp-input" value="<?= e($customer['company_name']) ?>">
                </div>

                <div class="cp-form-group">
                    <label class="cp-label">Telefoonnummer</label>
                    <input type="text" name="phone" class="cp-input" value="<?= e($customer['phone']) ?>">
                </div>

                <div class="cp-form-group">
                    <label class="cp-label">Adres</label>
                    <input type="text" name="address" class="cp-input" value="<?= e($customer['address']) ?>">
                </div>

                <div class="cp-form-row">
                    <div class="cp-form-group">
                        <label class="cp-label">Postcode</label>
                        <input type="text" name="postcode" class="cp-input" value="<?= e($customer['postcode']) ?>">
                    </div>
                    <div class="cp-form-group">
                        <label class="cp-label">Stad</label>
                        <input type="text" name="city" class="cp-input" value="<?= e($customer['city']) ?>">
                    </div>
                </div>

                <button type="submit" class="cp-btn cp-btn-primary">Opslaan</button>
            </form>
        </div>
    </div>
</div>

<?php require THEMES_PATH . $activeTheme . '/footer.php'; ?>
