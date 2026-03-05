<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "social_feed_posts");
