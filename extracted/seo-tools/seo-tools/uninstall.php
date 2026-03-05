<?php
$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "seo_meta`");
foreach (['seo_google_verify','seo_robots_txt','seo_sitemap_enabled'] as $k) {
    $db->delete(DB_PREFIX . 'settings', '`key` = ?', [$k]);
}
