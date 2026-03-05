<?php
function get_seo_meta(string $type, int $id): ?array {
    return Database::getInstance()->fetch(
        "SELECT * FROM `" . DB_PREFIX . "seo_meta` WHERE object_type=? AND object_id=?",
        [$type, $id]
    );
}
