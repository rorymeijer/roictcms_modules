<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "google_maps_locations");

Settings::delete('google_maps_api_key');
