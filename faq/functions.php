<?php
function faq_render_widget(int $categoryId = 0): string {
    $db     = Database::getInstance();
    $params = ['published'];
    $sql    = "SELECT f.*, c.name AS cat_name FROM `" . DB_PREFIX . "faq_items` f
               LEFT JOIN `" . DB_PREFIX . "faq_categories` c ON c.id=f.category_id
               WHERE f.status=?";
    if ($categoryId > 0) { $sql .= ' AND f.category_id=?'; $params[] = $categoryId; }
    $sql  .= ' ORDER BY f.sort_order, f.id';
    $items = $db->fetchAll($sql, $params);
    if (empty($items)) return '<p class="text-muted">Geen vragen gevonden.</p>';

    $id   = 'faqAcc' . uniqid();
    $html = '<div class="accordion faq-widget" id="' . $id . '">';
    foreach ($items as $i => $item) {
        $iid = $id . 'i' . $i;
        $cid = $id . 'c' . $i;
        $html .= '<div class="accordion-item">'
               . '<h2 class="accordion-header" id="' . $iid . '">'
               . '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"'
               . ' data-bs-target="#' . $cid . '" aria-expanded="false">'
               . e($item['question']) . '</button></h2>'
               . '<div id="' . $cid . '" class="accordion-collapse collapse" data-bs-parent="#' . $id . '">'
               . '<div class="accordion-body">' . nl2br(e($item['answer'])) . '</div></div></div>';
    }
    return $html . '</div>';
}
