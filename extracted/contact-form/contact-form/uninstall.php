<?php
/**
 * Contact Form Module — Uninstall Script
 */
$db = Database::getInstance();
$db->getPdo()->exec("DROP TABLE IF EXISTS `" . DB_PREFIX . "contact_messages`");

// Remove settings
$keys = ['contact_form_email','contact_form_subject','contact_form_honeypot','contact_form_ratelimit','contact_form_success_msg'];
foreach ($keys as $k) {
    $db->delete(DB_PREFIX . 'settings', '`key` = ?', [$k]);
}

return ['success' => true];
