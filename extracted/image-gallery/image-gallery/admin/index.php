<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Image Gallery';
$activePage = 'image-gallery';
$uploadDir = BASE_PATH . '/uploads/galleries';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/modules/image-gallery/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_gallery') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $id = $db->insert(DB_PREFIX . 'galleries', [
                'title'  => $title,
                'slug'   => slug($title),
                'status' => $_POST['status'] ?? 'published',
            ]);
            flash('success', 'Galerij aangemaakt.');
            redirect(BASE_URL . '/admin/modules/image-gallery/?gallery=' . $id);
        }
    }

    if ($action === 'delete_gallery') {
        $gid = (int)$_POST['gallery_id'];
        $db->delete(DB_PREFIX . 'gallery_images', 'gallery_id = ?', [$gid]);
        $db->delete(DB_PREFIX . 'galleries', 'id = ?', [$gid]);
        flash('success', 'Galerij verwijderd.');
        redirect(BASE_URL . '/admin/modules/image-gallery/');
    }

    if ($action === 'upload_image') {
        $gid  = (int)$_POST['gallery_id'];
        $file = $_FILES['image'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed, true)) {
                $fname = 'img_' . uniqid() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fname);
                $db->insert(DB_PREFIX . 'gallery_images', [
                    'gallery_id' => $gid,
                    'filename'   => $fname,
                    'caption'    => trim($_POST['caption'] ?? ''),
                ]);
                flash('success', 'Afbeelding geüpload.');
            } else {
                flash('error', 'Ongeldig bestandstype.');
            }
        }
        redirect(BASE_URL . '/admin/modules/image-gallery/?gallery=' . $gid);
    }

    if ($action === 'delete_image') {
        $img = $db->fetch("SELECT * FROM `" . DB_PREFIX . "gallery_images` WHERE id=?", [(int)$_POST['image_id']]);
        if ($img) {
            @unlink($uploadDir . '/' . $img['filename']);
            $db->delete(DB_PREFIX . 'gallery_images', 'id=?', [$img['id']]);
            flash('success', 'Afbeelding verwijderd.');
        }
        redirect(BASE_URL . '/admin/modules/image-gallery/?gallery=' . ($img['gallery_id'] ?? ''));
    }

    redirect(BASE_URL . '/admin/modules/image-gallery/');
}

$galleries = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "galleries` ORDER BY created_at DESC");
$selGid    = (int)($_GET['gallery'] ?? 0);
$selGallery = $selGid ? $db->fetch("SELECT * FROM `" . DB_PREFIX . "galleries` WHERE id=?", [$selGid]) : null;
$images    = $selGid ? $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "gallery_images` WHERE gallery_id=? ORDER BY sort_order,id", [$selGid]) : [];

require_once ADMIN_PATH . '/includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-images"></i> <?= e($pageTitle) ?></h1></div>
<?= renderFlash() ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><strong>Nieuwe galerij</strong></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_gallery">
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control" placeholder="Naam galerij" required>
                    </div>
                    <div class="mb-2">
                        <select name="status" class="form-select">
                            <option value="published">Gepubliceerd</option>
                            <option value="draft">Concept</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> Aanmaken</button>
                </form>
            </div>
        </div>
        <div class="list-group">
            <?php foreach ($galleries as $g): ?>
            <a href="?gallery=<?= $g['id'] ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between <?= $selGid === (int)$g['id'] ? 'active' : '' ?>">
                <span><?= e($g['title']) ?></span>
                <span class="badge bg-secondary"><?= (int)$db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "gallery_images` WHERE gallery_id=?", [$g['id']])['c'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-md-8">
        <?php if ($selGallery): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= e($selGallery['title']) ?></strong>
                <form method="POST" onsubmit="return confirm('Galerij verwijderen?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_gallery">
                    <input type="hidden" name="gallery_id" value="<?= $selGid ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Verwijderen</button>
                </form>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_image">
                    <input type="hidden" name="gallery_id" value="<?= $selGid ?>">
                    <div class="col-md-6">
                        <label class="form-label">Afbeelding uploaden</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bijschrift</label>
                        <input type="text" name="caption" class="form-control" placeholder="Optioneel">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
                    </div>
                </form>

                <div class="row g-2">
                    <?php foreach ($images as $img): ?>
                    <div class="col-6 col-md-4">
                        <div class="card h-100">
                            <img src="<?= e(BASE_URL . '/uploads/galleries/' . $img['filename']) ?>"
                                 class="card-img-top" style="height:120px;object-fit:cover;" alt="">
                            <div class="card-body p-2">
                                <p class="small text-muted mb-1"><?= e($img['caption'] ?: '—') ?></p>
                                <form method="POST" onsubmit="return confirm('Verwijderen?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($images)): ?>
                        <div class="col-12 text-center text-muted py-3">Nog geen afbeeldingen.</div>
                    <?php endif; ?>
                </div>

                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Gebruik in thema:<br>
                    <code>&lt;?php require_once MODULES_PATH.'/image-gallery/functions.php'; echo gallery_render(<?= $selGid ?>); ?&gt;</code></small>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="card"><div class="card-body text-center text-muted py-5">Selecteer of maak een galerij aan.</div></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
