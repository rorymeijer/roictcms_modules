<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();
require_once dirname(__DIR__) . '/functions.php';

$db        = Database::getInstance();
$pageTitle = 'SEO Tools';
$activePage = 'seo-tools';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/seo-tools/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_meta') {
        $type = $_POST['object_type'] ?? 'page';
        $id   = (int)($_POST['object_id'] ?? 0);
        if ($id > 0) {
            $data = [
                'object_type'      => $type,
                'object_id'        => $id,
                'meta_title'       => trim($_POST['meta_title'] ?? ''),
                'meta_description' => trim($_POST['meta_description'] ?? ''),
                'meta_keywords'    => trim($_POST['meta_keywords'] ?? ''),
                'og_title'         => trim($_POST['og_title'] ?? ''),
                'og_description'   => trim($_POST['og_description'] ?? ''),
                'og_image'         => trim($_POST['og_image'] ?? ''),
                'canonical_url'    => trim($_POST['canonical_url'] ?? ''),
                'no_index'         => isset($_POST['no_index']) ? 1 : 0,
            ];
            $existing = $db->fetch(
                "SELECT id FROM `" . DB_PREFIX . "seo_meta` WHERE object_type=? AND object_id=?",
                [$type, $id]
            );
            if ($existing) {
                $db->update(DB_PREFIX . 'seo_meta', $data, 'id=?', [$existing['id']]);
            } else {
                $db->insert(DB_PREFIX . 'seo_meta', $data);
            }
            flash('success', 'SEO-instellingen opgeslagen.');
        }
    }

    if ($action === 'save_global') {
        Settings::set('seo_google_verify',  trim($_POST['google_verify'] ?? ''));
        Settings::set('seo_sitemap_enabled', isset($_POST['sitemap_enabled']) ? '1' : '0');
        Settings::set('seo_robots_txt',      $_POST['robots_txt'] ?? '');
        flash('success', 'Algemene SEO-instellingen opgeslagen.');
    }

    redirect(BASE_URL . '/admin/seo-tools/');
}

$tab   = $_GET['tab'] ?? 'pages';
$pages = $db->fetchAll("SELECT id, title FROM `" . DB_PREFIX . "pages` ORDER BY title");
$selId = (int)($_GET['page_id'] ?? ($pages[0]['id'] ?? 0));
$seo   = $selId ? get_seo_meta('page', $selId) : null;

require_once ADMIN_PATH . '/includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-search"></i> <?= e($pageTitle) ?></h1></div>
<?= renderFlash() ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'pages'  ? 'active' : '' ?>" href="?tab=pages">Pagina-meta</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'global' ? 'active' : '' ?>" href="?tab=global">Algemeen</a></li>
</ul>

<?php if ($tab === 'pages'): ?>
<div class="row g-3">
    <div class="col-md-3">
        <div class="list-group">
            <?php foreach ($pages as $p): ?>
            <a href="?tab=pages&page_id=<?= $p['id'] ?>"
               class="list-group-item list-group-item-action <?= $selId === (int)$p['id'] ? 'active' : '' ?>">
                <?= e($p['title']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-md-9">
        <?php if ($selId): ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_meta">
                    <input type="hidden" name="object_type" value="page">
                    <input type="hidden" name="object_id" value="<?= $selId ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">SEO-titel</label>
                            <input type="text" name="meta_title" class="form-control" value="<?= e($seo['meta_title'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meta-omschrijving</label>
                            <textarea name="meta_description" class="form-control" rows="2"><?= e($seo['meta_description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meta-trefwoorden</label>
                            <input type="text" name="meta_keywords" class="form-control" value="<?= e($seo['meta_keywords'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">OG-titel</label>
                            <input type="text" name="og_title" class="form-control" value="<?= e($seo['og_title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">OG-afbeelding (URL)</label>
                            <input type="text" name="og_image" class="form-control" value="<?= e($seo['og_image'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">OG-omschrijving</label>
                            <textarea name="og_description" class="form-control" rows="2"><?= e($seo['og_description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Canonieke URL</label>
                            <input type="text" name="canonical_url" class="form-control" value="<?= e($seo['canonical_url'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="no_index" id="noindex"
                                       <?= !empty($seo['no_index']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="noindex">Noindex (verberg voor zoekmachines)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_global">
            <div class="mb-3">
                <label class="form-label">Google Search Console verificatiecode</label>
                <input type="text" name="google_verify" class="form-control"
                       value="<?= e(Settings::get('seo_google_verify', '')) ?>"
                       placeholder="abc123def456...">
                <div class="form-text">Alleen de content-waarde van de verificatie-metatag.</div>
            </div>
            <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" name="sitemap_enabled" id="sitemapEnabled"
                       <?= Settings::get('seo_sitemap_enabled', '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="sitemapEnabled">Sitemap.xml inschakelen</label>
                <div class="form-text">Bereikbaar via <code><?= e(BASE_URL) ?>/?sitemap=xml</code></div>
            </div>
            <div class="mb-3">
                <label class="form-label">robots.txt inhoud</label>
                <textarea name="robots_txt" class="form-control font-monospace" rows="6"><?= e(Settings::get('seo_robots_txt', "User-agent: *\nAllow: /")) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Opslaan</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
