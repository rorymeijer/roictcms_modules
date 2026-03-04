<?php
/**
 * Reacties Module — Admin Paneel
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Reacties';
$activePage = 'comments';

// ── Acties (goedkeuren / spam / verwijderen) ───────────────────────────────
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($action && $id && csrf_verify()) {
    switch ($action) {
        case 'approve':
            $db->update(DB_PREFIX . 'comments', ['status' => 'approved'], 'id = ?', [$id]);
            flash('success', 'Reactie goedgekeurd.');
            break;
        case 'spam':
            $db->update(DB_PREFIX . 'comments', ['status' => 'spam'], 'id = ?', [$id]);
            flash('success', 'Reactie als spam gemarkeerd.');
            break;
        case 'delete':
            $db->delete(DB_PREFIX . 'comments', 'id = ?', [$id]);
            flash('success', 'Reactie verwijderd.');
            break;
        case 'delete_all_spam':
            $db->getPdo()->exec("DELETE FROM `" . DB_PREFIX . "comments` WHERE status = 'spam'");
            flash('success', 'Alle spam verwijderd.');
            break;
    }
    redirect(BASE_URL . '/admin/modules/comments/?tab=' . ($_GET['tab'] ?? 'pending'));
}

// ── Instellingen opslaan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings' && csrf_verify()) {
    Settings::set('comments_moderation',  isset($_POST['cm_moderation']) ? '1' : '0');
    Settings::set('comments_honeypot',    isset($_POST['cm_honeypot'])   ? '1' : '0');
    Settings::set('comments_ratelimit',   (int)($_POST['cm_ratelimit']   ?? 3));
    Settings::set('comments_success_msg', trim($_POST['cm_success_msg']  ?? ''));
    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . '/admin/modules/comments/?tab=settings');
}

$tab = $_GET['tab'] ?? 'pending';

// ── Tellingen voor badges ──────────────────────────────────────────────────
$counts = [
    'pending'  => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "comments` WHERE status = 'pending'")['c'],
    'approved' => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "comments` WHERE status = 'approved'")['c'],
    'spam'     => $db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "comments` WHERE status = 'spam'")['c'],
];

// ── Reacties ophalen ───────────────────────────────────────────────────────
$statusFilter = match($tab) {
    'approved' => 'approved',
    'spam'     => 'spam',
    default    => 'pending',
};

$comments = $statusFilter !== 'settings'
    ? $db->fetchAll(
        "SELECT * FROM `" . DB_PREFIX . "comments`
         WHERE status = ? ORDER BY created_at DESC",
        [$statusFilter]
      )
    : [];

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
      <i class="bi bi-chat-dots me-2" style="color:var(--primary);"></i>Reacties
    </h1>
    <p class="text-muted mb-0" style="font-size:.85rem;">Modereer bezoekerreacties</p>
  </div>
  <a href="<?= BASE_URL ?>/admin/modules/" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Terug naar modules
  </a>
</div>

<!-- Tabs -->
<div class="mb-4 d-flex gap-2 flex-wrap">
<?php
$tabs = [
    'pending'  => ['label' => 'In behandeling', 'icon' => 'hourglass-split', 'count' => $counts['pending'],  'urgent' => true],
    'approved' => ['label' => 'Goedgekeurd',    'icon' => 'check-circle',    'count' => $counts['approved'], 'urgent' => false],
    'spam'     => ['label' => 'Spam',           'icon' => 'shield-x',        'count' => $counts['spam'],     'urgent' => false],
    'settings' => ['label' => 'Instellingen',   'icon' => 'gear',            'count' => null,                'urgent' => false],
];
foreach ($tabs as $key => $t):
    $active = $tab === $key;
?>
<a href="?tab=<?= $key ?>"
   style="display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1.1rem;border-radius:10px;text-decoration:none;font-size:.875rem;font-weight:600;border:1.5px solid <?= $active ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $active ? 'var(--primary)' : 'white' ?>;color:<?= $active ? 'white' : 'var(--text-muted)' ?>;">
  <i class="bi bi-<?= $t['icon'] ?>"></i>
  <?= $t['label'] ?>
  <?php if ($t['count'] !== null): ?>
  <span style="background:<?= $active ? 'rgba(255,255,255,.25)' : 'var(--surface)' ?>;border-radius:999px;padding:.1rem .5rem;font-size:.7rem;font-weight:700;"><?= $t['count'] ?></span>
  <?php endif; ?>
  <?php if ($t['urgent'] && $t['count'] > 0): ?>
  <span style="background:#ef4444;color:white;border-radius:999px;padding:.1rem .45rem;font-size:.65rem;font-weight:700;"><?= $t['count'] ?></span>
  <?php endif; ?>
</a>
<?php endforeach; ?>
</div>

<?php if ($tab === 'settings'): ?>
<!-- ──── INSTELLINGEN ──────────────────────────────────────────────────── -->
<div class="row justify-content-center"><div class="col-md-8">
<div class="cms-card">
  <div class="cms-card-header"><span class="cms-card-title">Module instellingen</span></div>
  <div class="cms-card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="settings">

      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="cm_moderation" id="cm_mod" value="1"
                 <?= Settings::get('comments_moderation', '1') ? 'checked' : '' ?>>
          <label class="form-check-label fw-semibold" for="cm_mod">Moderatie inschakelen</label>
        </div>
        <div class="form-text">Reacties worden pas zichtbaar na goedkeuring door een beheerder.</div>
      </div>

      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="cm_honeypot" id="cm_hp" value="1"
                 <?= Settings::get('comments_honeypot', '1') ? 'checked' : '' ?>>
          <label class="form-check-label fw-semibold" for="cm_hp">Honeypot spambeveiliging</label>
        </div>
        <div class="form-text">Verborgen veld dat bots automatisch in de val lokt.</div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Rate limit (reacties per uur per IP)</label>
        <input type="number" class="form-control" name="cm_ratelimit"
               value="<?= e(Settings::get('comments_ratelimit', '3')) ?>"
               min="1" max="50" style="max-width:120px;">
        <div class="form-text">Maximaal aantal reacties dat één bezoeker per uur mag plaatsen.</div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Succesbericht</label>
        <textarea class="form-control" name="cm_success_msg" rows="3"><?= e(Settings::get('comments_success_msg', 'Bedankt voor uw reactie!')) ?></textarea>
        <div class="form-text">Getoond na het succesvol plaatsen van een reactie.</div>
      </div>

      <hr class="my-4">
      <h6 class="fw-bold mb-3">Gebruik in een thema template</h6>
      <div style="background:#1e293b;color:#e2e8f0;padding:1rem 1.25rem;border-radius:10px;font-family:monospace;font-size:.85rem;line-height:1.7;">
        <span style="color:#94a3b8;">&lt;?php</span><br>
        <span style="color:#7dd3fc;">// Voeg reacties toe aan een pagina template:</span><br>
        <span style="color:#a5f3fc;">if (function_exists('comments_section')) {</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#86efac;">echo comments_section();</span><br>
        <span style="color:#a5f3fc;">}</span><br>
        <span style="color:#94a3b8;">?&gt;</span><br><br>
        <span style="color:#7dd3fc;">// Of via shortcode in paginacontent:</span><br>
        <span style="color:#fbbf24;">[comments]</span>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Opslaan</button>
      </div>
    </form>
  </div>
</div>
</div></div>

<?php else: ?>
<!-- ──── REACTIES LIJST ────────────────────────────────────────────────── -->

<?php if ($tab === 'spam' && $counts['spam'] > 0): ?>
<div class="mb-3 text-end">
  <a href="?tab=spam&action=delete_all_spam&csrf_token=<?= csrf_token() ?>"
     class="btn btn-sm btn-outline-danger"
     data-confirm="Alle spam definitief verwijderen?">
    <i class="bi bi-trash me-1"></i>Alle spam verwijderen
  </a>
</div>
<?php endif; ?>

<div class="cms-card">
  <?php if (!$comments): ?>
  <div class="cms-card-body text-center py-5">
    <i class="bi bi-chat-dots" style="font-size:2.5rem;display:block;opacity:.3;margin-bottom:.75rem;"></i>
    <p class="text-muted mb-0">
      <?php if ($tab === 'pending'): ?>Geen reacties in behandeling.
      <?php elseif ($tab === 'approved'): ?>Nog geen goedgekeurde reacties.
      <?php else: ?>Geen spam gevonden.<?php endif; ?>
    </p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.875rem;">
      <thead>
        <tr style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">
          <th style="padding:1rem 1.25rem;border-bottom:1.5px solid var(--border);">Auteur</th>
          <th style="padding:1rem 1.25rem;border-bottom:1.5px solid var(--border);">Reactie</th>
          <th style="padding:1rem 1.25rem;border-bottom:1.5px solid var(--border);">Pagina</th>
          <th style="padding:1rem 1.25rem;border-bottom:1.5px solid var(--border);">Datum</th>
          <th style="padding:1rem 1.25rem;border-bottom:1.5px solid var(--border);text-align:right;">Acties</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($comments as $c): ?>
        <tr>
          <td style="padding:1rem 1.25rem;vertical-align:top;">
            <div style="font-weight:700;"><?= e($c['author_name']) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted);"><?= e($c['author_email']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);font-family:monospace;"><?= e($c['ip_address']) ?></div>
          </td>
          <td style="padding:1rem 1.25rem;vertical-align:top;max-width:340px;">
            <div style="color:#292524;line-height:1.55;white-space:pre-wrap;"><?= e(mb_strimwidth($c['content'], 0, 200, '…')) ?></div>
          </td>
          <td style="padding:1rem 1.25rem;vertical-align:top;">
            <span style="font-size:.78rem;font-family:monospace;color:var(--text-muted);word-break:break-all;"><?= e($c['post_id']) ?></span>
          </td>
          <td style="padding:1rem 1.25rem;vertical-align:top;white-space:nowrap;">
            <span style="font-size:.8rem;"><?= date('d M Y', strtotime($c['created_at'])) ?></span><br>
            <span style="font-size:.75rem;color:var(--text-muted);"><?= date('H:i', strtotime($c['created_at'])) ?></span>
          </td>
          <td style="padding:1rem 1.25rem;vertical-align:top;text-align:right;white-space:nowrap;">
            <div class="d-flex gap-1 justify-content-end">
              <?php if ($tab === 'pending'): ?>
              <a href="?tab=<?= $tab ?>&action=approve&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>"
                 class="btn btn-sm btn-success" title="Goedkeuren">
                <i class="bi bi-check-lg"></i>
              </a>
              <a href="?tab=<?= $tab ?>&action=spam&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>"
                 class="btn btn-sm btn-outline-secondary" title="Markeer als spam">
                <i class="bi bi-shield-x"></i>
              </a>
              <?php elseif ($tab === 'approved'): ?>
              <a href="?tab=<?= $tab ?>&action=spam&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>"
                 class="btn btn-sm btn-outline-secondary" title="Markeer als spam">
                <i class="bi bi-shield-x"></i>
              </a>
              <?php elseif ($tab === 'spam'): ?>
              <a href="?tab=<?= $tab ?>&action=approve&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>"
                 class="btn btn-sm btn-outline-success" title="Goedkeuren">
                <i class="bi bi-check-lg"></i>
              </a>
              <?php endif; ?>
              <a href="?tab=<?= $tab ?>&action=delete&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>"
                 class="btn btn-sm btn-outline-danger" title="Verwijderen"
                 data-confirm="Reactie definitief verwijderen?">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
