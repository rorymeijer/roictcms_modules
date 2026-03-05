<?php
$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gallery_images`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "galleries`");
