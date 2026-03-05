<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();
$db = Database::getInstance();
$pageTitle = 'Contact Formulier';
$activePage = 'modules';

// Acties
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($action && $id && csrf_verify()) {
    switch ($action) {
        case 'read':
            $db->update(DB_PREFIX . 'contact_messages', ['status' => 'read'], 'id = ?', [$id]);
            flash('success', 'Gemarkeerd als gelezen.');
            break;
        case 'spam':
            $db->update(DB_PREFIX . 'contact_messages', ['status' => 'spam'], 'id = ?', [$id]);
            flash('success', 'Gemarkeerd als spam.');
            break;
        case 'archive':
            $db->update(DB_PREFIX . 'contact_messages', ['status' => 'archived'], 'id = ?', [$id]);
            flash('success', 'Bericht gearchiveerd.');
            break;
        case 'delete':
            $db->delete(DB_PREFIX . 'contact_messages', 'id = ?', [$id]);
            flash('success', 'Bericht verwijderd.');
            break;
    }
    redirect(BASE_URL . '/admin/modules/contact-form/?tab=' . ($_GET['tab'] ?? 'inbox'));
}

// Instellingen opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings' && csrf_verify()) {
    Settings::set('contact_form_email',       trim($_POST['cf_email']       ?? ''));
    Settings::set('contact_form_subject',     trim($_POST['cf_subject']     ?? ''));
    Settings::set('contact_form_honeypot',    isset($_POST['cf_honeypot']) ? '1' : '0');
    Settings::set('contact_form_ratelimit',   (int)($_POST['cf_ratelimit']  ?? 5));
    Settings::set('contact_form_success_msg', trim($_POST['cf_success_msg'] ?? ''));
    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . '/admin/modules/contact-form/?tab=settings');
}

$tab = $_GET['tab'] ?? 'inbox';

// Berichten ophalen
$filter = match($tab) {
    'spam'     => "status = 'spam'",
    'archived' => "status = 'archived'",
    default    => "status IN ('unread','read')",
};

$messages = $db->fetchAll(
    "SELECT * FROM `" . DB_PREFIX . "contact_messages` WHERE {$filter} ORDER BY created_at DESC"
);

// Markeer geopend bericht als gelezen
$viewMsg = null;
if (isset($_GET['view'])) {
    $viewMsg = $db->fetch("SELECT * FROM `" . DB_PREFIX . "contact_messages` WHERE id = ?", [(int)$_GET['view']]);
    if ($viewMsg && $viewMsg['status'] === 'unread') {
        $db->update(DB_PREFIX . 'contact_messages', ['status' => 'read'], 'id = ?', [$viewMsg['id']]);
        $viewMsg['status'] = 'read';
    }
}

// Counts voor badges
$counts = [
    'unread'   => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "contact_messages` WHERE status = 'unread'")['c'],
    'inbox'    => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "contact_messages` WHERE status IN ('unread','read')")['c'],
    'spam'     => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "contact_messages` WHERE status = 'spam'")['c'],
    'archived' => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "contact_messages` WHERE status = 'archived'")['c'],
];

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
      <i class="bi bi-envelope me-2" style="color:var(--primary);"></i>Contact Formulier
    </h1>
    <p class="text-muted mb-0" style="font-size:.85rem;">Ontvangen berichten en instellingen</p>
  </div>
  <a href="<?= BASE_URL ?>/admin/modules/" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Terug naar modules
  </a>
</div>

<!-- Tabs -->
<div class="mb-4 d-flex gap-2 flex-wrap">
  <?php
  $tabs = [
    'inbox'    => ['label' => 'Inbox',     'icon' => 'inbox',    'count' => $counts['inbox'],    'badge_unread' => $counts['unread']],
    'spam'     => ['label' => 'Spam',      'icon' => 'shield-x', 'count' => $counts['spam'],     'badge_unread' => 0],
    'archived' => ['label' => 'Archief',   'icon' => 'archive',  'count' => $counts['archived'], 'badge_unread' => 0],
    'settings' => ['label' => 'Instellingen', 'icon' => 'gear',  'count' => null,                'badge_unread' => 0],
  ];
  foreach ($tabs as $key => $t):
    $active = $tab === $key;
  ?>
  <a href="?tab=<?= $key ?>" style="display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1.1rem;border-radius:10px;text-decoration:none;font-size:.875rem;font-weight:600;border:1.5px solid <?= $active ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $active ? 'var(--primary)' : 'white' ?>;color:<?= $active ? 'white' : 'var(--text-muted)' ?>;">
    <i class="bi bi-<?= $t['icon'] ?>"></i>
    <?= $t['label'] ?>
    <?php if ($t['count'] !== null): ?>
    <span style="background:<?= $active ? 'rgba(255,255,255,.25)' : 'var(--surface)' ?>;border-radius:999px;padding:.1rem .5rem;font-size:.7rem;font-weight:700;">
      <?= $t['count'] ?>
    </span>
    <?php endif; ?>
    <?php if ($t['badge_unread'] > 0): ?>
    <span style="background:#ef4444;color:white;border-radius:999px;padding:.1rem .45rem;font-size:.65rem;font-weight:700;"><?= $t['badge_unread'] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'settings'): ?>
<!-- ──── INSTELLINGEN ──────────────────────────────────────────── -->
<div class="row justify-content-center"><div class="col-md-8">
<div class="cms-card">
  <div class="cms-card-header"><span class="cms-card-title">Formulier instellingen</span></div>
  <div class="cms-card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="settings">
      <div class="mb-3">
        <label class="form-label">E-mailadres notificaties</label>
        <input type="email" class="form-control" name="cf_email" value="<?= e(Settings::get('contact_form_email')) ?>" placeholder="admin@uw-domein.nl">
        <div class="form-text">Naar dit adres worden nieuwe berichten doorgestuurd.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">E-mail onderwerp</label>
        <input type="text" class="form-control" name="cf_subject" value="<?= e(Settings::get('contact_form_subject')) ?>">
        <div class="form-text">Gebruik <code>{site_name}</code> als variabele.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Succesbericht</label>
        <textarea class="form-control" name="cf_success_msg" rows="3"><?= e(Settings::get('contact_form_success_msg')) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Rate limit (berichten per uur per IP)</label>
        <input type="number" class="form-control" name="cf_ratelimit" value="<?= e(Settings::get('contact_form_ratelimit', 5)) ?>" min="1" max="100" style="max-width:120px;">
      </div>
      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="cf_honeypot" id="cf_hp" value="1" <?= Settings::get('contact_form_honeypot', '1') ? 'checked' : '' ?>>
          <label class="form-check-label fw-semibold" for="cf_hp">Honeypot spambeveiliging inschakelen</label>
        </div>
        <div class="form-text">Verborgen veld dat bots in de val lokt.</div>
      </div>

      <hr class="my-4">
      <h6 class="fw-bold mb-3">Gebruik in een thema template</h6>
      <div style="background:#1e293b;color:#e2e8f0;padding:1rem 1.25rem;border-radius:10px;font-family:monospace;font-size:.85rem;line-height:1.7;">
        <span style="color:#94a3b8;">&lt;?php</span><br>
        <span style="color:#7dd3fc;">// Voeg dit toe aan een pagina template in uw thema:</span><br>
        <span style="color:#a5f3fc;">if (function_exists('contact_form')) {</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#86efac;">echo contact_form();</span><br>
        <span style="color:#a5f3fc;">}</span><br>
        <span style="color:#94a3b8;">?&gt;</span>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Opslaan</button>
      </div>
    </form>
  </div>
</div>
</div></div>

<?php else: ?>
<!-- ──── INBOX / SPAM / ARCHIEF ───────────────────────────────── -->
<div class="row g-3">
  <!-- Berichtenlijst -->
  <div class="col-md-<?= $viewMsg ? '4' : '12' ?>">
    <div class="cms-card">
      <?php if (!$messages): ?>
      <div class="cms-card-body text-center py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem;display:block;opacity:.3;margin-bottom:.75rem;"></i>
        <p class="text-muted mb-0">Geen berichten <?= $tab === 'spam' ? 'in spam' : ($tab === 'archived' ? 'in archief' : 'in inbox') ?>.</p>
      </div>
      <?php else: ?>
      <div>
        <?php foreach ($messages as $msg):
          $isActive = isset($_GET['view']) && (int)$_GET['view'] === $msg['id'];
        ?>
        <a href="?tab=<?= $tab ?>&view=<?= $msg['id'] ?>"
           style="display:flex;gap:.85rem;padding:1rem 1.25rem;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;background:<?= $isActive ? 'rgba(37,99,235,.05)' : 'transparent' ?>;transition:background .15s;"
           class="<?= $isActive ? 'msg-active' : '' ?>">
          <div style="flex-shrink:0;width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.9rem;">
            <?= strtoupper(substr($msg['name'], 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;">
              <span style="font-weight:<?= $msg['status'] === 'unread' ? '700' : '500' ?>;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($msg['name']) ?></span>
              <span style="font-size:.72rem;color:var(--text-muted);flex-shrink:0;"><?= date('d M', strtotime($msg['created_at'])) ?></span>
            </div>
            <div style="font-size:.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($msg['subject']) ?></div>
            <?php if ($msg['status'] === 'unread'): ?>
            <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--primary);margin-top:.25rem;"></span>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Berichtdetail -->
  <?php if ($viewMsg): ?>
  <div class="col-md-8">
    <div class="cms-card">
      <div class="cms-card-header">
        <span class="cms-card-title"><?= e($viewMsg['subject']) ?></span>
        <div class="d-flex gap-2">
          <?php if ($tab === 'inbox'): ?>
          <a href="?tab=<?= $tab ?>&view=<?= $viewMsg['id'] ?>&action=archive&id=<?= $viewMsg['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-secondary" title="Archiveren"><i class="bi bi-archive"></i></a>
          <a href="?tab=<?= $tab ?>&view=<?= $viewMsg['id'] ?>&action=spam&id=<?= $viewMsg['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-secondary" title="Markeer als spam"><i class="bi bi-shield-x"></i></a>
          <?php endif; ?>
          <a href="mailto:<?= e($viewMsg['email']) ?>?subject=Re: <?= urlencode($viewMsg['subject']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-reply me-1"></i>Beantwoorden</a>
          <a href="?tab=<?= $tab ?>&action=delete&id=<?= $viewMsg['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" data-confirm="Bericht definitief verwijderen?"><i class="bi bi-trash"></i></a>
        </div>
      </div>
      <div class="cms-card-body">
        <!-- Afzender info -->
        <div style="display:flex;gap:1rem;padding:1rem;background:var(--surface);border-radius:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
          <div style="flex:1;min-width:200px;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:var(--text-muted);margin-bottom:.2rem;">Van</div>
            <div style="font-weight:700;"><?= e($viewMsg['name']) ?></div>
            <a href="mailto:<?= e($viewMsg['email']) ?>" style="font-size:.85rem;color:var(--primary);"><?= e($viewMsg['email']) ?></a>
          </div>
          <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:var(--text-muted);margin-bottom:.2rem;">Ontvangen</div>
            <div style="font-size:.88rem;"><?= date('d M Y H:i', strtotime($viewMsg['created_at'])) ?></div>
          </div>
          <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:var(--text-muted);margin-bottom:.2rem;">IP-adres</div>
            <div style="font-size:.88rem;font-family:monospace;"><?= e($viewMsg['ip_address']) ?></div>
          </div>
        </div>
        <!-- Bericht -->
        <div style="font-size:.95rem;line-height:1.8;white-space:pre-wrap;color:#292524;"><?= e($viewMsg['message']) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
