<?php
$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "newsletter_campaigns`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "newsletter_subscribers`");
foreach (['newsletter_from_name','newsletter_from_email'] as $k) {
    $db->delete(DB_PREFIX . 'settings', '`key` = ?', [$k]);
}
