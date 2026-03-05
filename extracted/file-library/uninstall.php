<?php
/**
 * File Library Module - Uninstall
 * Verwijdert de databasetabel en geüploade bestanden.
 */

$db = Database::getInstance();

// Verwijder geüploade bestanden
$uploadDir = UPLOADS_PATH . '/file-library/';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($uploadDir);
}

$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "file_library_files");
