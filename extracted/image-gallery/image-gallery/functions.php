<?php
function gallery_render(int $galleryId): string {
    $db     = Database::getInstance();
    $images = $db->fetchAll(
        "SELECT * FROM `" . DB_PREFIX . "gallery_images` gi
         JOIN `" . DB_PREFIX . "galleries` g ON g.id = gi.gallery_id
         WHERE gi.gallery_id = ? AND g.status = 'published'
         ORDER BY gi.sort_order, gi.id",
        [$galleryId]
    );
    if (empty($images)) return '';

    $html = '<div class="gallery-grid">';
    foreach ($images as $img) {
        $src = BASE_URL . '/uploads/galleries/' . e($img['filename']);
        $cap = e($img['caption'] ?? '');
        $html .= '<div class="gallery-item" data-src="' . $src . '" data-caption="' . $cap . '">'
               . '<img src="' . $src . '" alt="' . $cap . '" loading="lazy">'
               . ($cap ? '<span class="caption">' . $cap . '</span>' : '')
               . '</div>';
    }
    $html .= '</div>';
    return $html;
}
