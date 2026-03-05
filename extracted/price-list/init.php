<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class PriceListModule
{
    public static function renderPriceList(?string $category = null): string
    {
        $db = Database::getInstance();

        if ($category !== null) {
            $stmt = $db->query("
                SELECT c.*, pi.id AS item_id, pi.name AS item_name, pi.description AS item_desc,
                       pi.price, pi.unit, pi.sort_order AS item_sort
                FROM " . DB_PREFIX . "price_categories c
                JOIN " . DB_PREFIX . "price_items pi ON pi.category_id = c.id AND pi.status = 'active'
                WHERE LOWER(c.name) = LOWER(?)
                ORDER BY c.sort_order ASC, pi.sort_order ASC, pi.id ASC
            ", [$category]);
        } else {
            $stmt = $db->query("
                SELECT c.*, pi.id AS item_id, pi.name AS item_name, pi.description AS item_desc,
                       pi.price, pi.unit, pi.sort_order AS item_sort
                FROM " . DB_PREFIX . "price_categories c
                JOIN " . DB_PREFIX . "price_items pi ON pi.category_id = c.id AND pi.status = 'active'
                ORDER BY c.sort_order ASC, c.id ASC, pi.sort_order ASC, pi.id ASC
            ");
        }

        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return '<p class="price-list-empty text-muted">Geen prijslijst beschikbaar.</p>';
        }

        // Group by category
        $categories = [];
        foreach ($rows as $row) {
            $catId = $row['id'];
            if (!isset($categories[$catId])) {
                $categories[$catId] = [
                    'name'  => $row['name'],
                    'items' => [],
                ];
            }
            $categories[$catId]['items'][] = $row;
        }

        $html = '<div class="price-list">';
        foreach ($categories as $cat) {
            $html .= '<div class="price-category mb-5">';
            $html .= '<h3 class="price-category-title">' . e($cat['name']) . '</h3>';
            $html .= '<table class="table price-table">';
            $html .= '<tbody>';
            foreach ($cat['items'] as $item) {
                $html .= '<tr class="price-item">';
                $html .= '<td class="price-item-name">';
                $html .= '<strong>' . e($item['item_name']) . '</strong>';
                if (!empty($item['item_desc'])) {
                    $html .= '<br><small class="text-muted">' . e($item['item_desc']) . '</small>';
                }
                $html .= '</td>';
                $html .= '<td class="price-item-price text-end">';
                $html .= '<span class="price-amount">' . e($item['price']) . '</span>';
                if (!empty($item['unit'])) {
                    $html .= '<br><small class="text-muted">' . e($item['unit']) . '</small>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/price-list/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-tag me-2"></i>Prijslijst</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/price-list/assets/css/price-list.css">';
});

// Register shortcode: [price_list] or [price_list category="diensten"]
add_shortcode('price_list', function ($atts) {
    $category = isset($atts['category']) ? (string)$atts['category'] : null;
    return PriceListModule::renderPriceList($category);
});
