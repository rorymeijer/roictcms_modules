<?php
/**
 * Testimonials Module - Uninstall
 * Verwijdert de databasetabel.
 */

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "testimonials");
