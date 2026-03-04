<?php
$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "faq_items`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "faq_categories`");
