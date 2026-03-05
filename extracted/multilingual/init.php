<?php
/**
 * Meertaligheid Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class MultilingualModule
{
    /**
     * Haal vertaling op. Fallback naar originele waarde als niet gevonden.
     */
    public static function getTranslation(string $type, int $id, string $field, string $langCode): ?string
    {
        $db = Database::getInstance();
        $val = $db->fetchOne(
            "SELECT translated_value FROM " . DB_PREFIX . "translations
              WHERE language_code = ? AND content_type = ? AND content_id = ? AND field_name = ?",
            [$langCode, $type, $id, $field]
        );
        return $val !== false ? $val : null;
    }

    /**
     * Sla een vertaling op (upsert).
     */
    public static function setTranslation(string $type, int $id, string $field, string $langCode, string $value): void
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "translations
              WHERE language_code = ? AND content_type = ? AND content_id = ? AND field_name = ?",
            [$langCode, $type, $id, $field]
        );
        if ($existing) {
            $db->query(
                "UPDATE " . DB_PREFIX . "translations SET translated_value = ? 
                  WHERE language_code = ? AND content_type = ? AND content_id = ? AND field_name = ?",
                [$value, $langCode, $type, $id, $field]
            );
        } else {
            $db->insert(DB_PREFIX . 'translations', [
                'language_code'    => $langCode,
                'content_type'     => $type,
                'content_id'       => $id,
                'field_name'       => $field,
                'translated_value' => $value,
            ]);
        }
    }

    /**
     * Haal alle actieve talen op.
     */
    public static function getLanguages(bool $activeOnly = true): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . DB_PREFIX . "languages";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $db->fetchAll($sql) ?: [];
    }

    /**
     * Render de taalwisselaar HTML.
     */
    public static function renderSwitcher(): string
    {
        $languages = self::getLanguages(true);
        if (empty($languages)) {
            return '';
        }

        $current = $_GET['lang'] ?? '';
        $html  = '<div class="language-switcher d-flex gap-1">';
        foreach ($languages as $lang) {
            $activeClass = ($lang['code'] === $current) ? ' fw-bold' : '';
            $url = htmlspecialchars(
                strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . '?lang=' . urlencode($lang['code']),
                ENT_QUOTES, 'UTF-8'
            );
            $html .= '<a href="' . $url . '" class="btn btn-sm btn-outline-secondary' . $activeClass . '">'
                . htmlspecialchars($lang['flag_emoji'], ENT_QUOTES, 'UTF-8') . ' '
                . htmlspecialchars($lang['code'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

// Sidebar
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/multilingual/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-translate me-2"></i>Meertaligheid'
        . '</a></li>';
});

// Taalwisselaar in theme head
add_action('theme_head', function () {
    $db = Database::getInstance();
    $show = $db->fetchOne(
        "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'multilingual_show_switcher'"
    );
    if ($show === '1') {
        echo '<style>.language-switcher{display:inline-flex;gap:.25rem}</style>';
    }
});
