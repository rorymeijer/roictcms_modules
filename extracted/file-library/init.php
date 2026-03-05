<?php
/**
 * File Library Module - Init
 * Registreert hooks, shortcodes, download handler en de FileLibraryModule klasse.
 */

class FileLibraryModule
{
    /**
     * Rendert een lijst van actieve bestanden met downloadknoppen.
     */
    public static function renderList(): string
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "file_library_files WHERE status = 'active' ORDER BY created_at DESC");
        $files = $stmt->fetchAll();

        if (empty($files)) {
            return '<p class="text-muted">Geen bestanden beschikbaar.</p>';
        }

        $html = '<div class="roict-file-library list-group">';
        foreach ($files as $file) {
            $downloadUrl = BASE_URL . '/?fl_download=' . (int)$file['id'];
            $sizeFormatted = self::formatFileSize((int)$file['file_size']);
            $icon = self::getFileIcon($file['file_type']);

            $html .= '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
            $html .= '<div>';
            $html .= '<i class="bi bi-' . $icon . ' me-2 text-primary fs-5"></i>';
            $html .= '<strong>' . e($file['title']) . '</strong>';
            if (!empty($file['description'])) {
                $html .= '<br><small class="text-muted ms-4">' . e($file['description']) . '</small>';
            }
            $html .= '<br><small class="text-muted ms-4">' . e($file['original_name']) . ' &bull; ' . $sizeFormatted . ' &bull; ' . (int)$file['download_count'] . 'x gedownload</small>';
            $html .= '</div>';
            $html .= '<a href="' . e($downloadUrl) . '" class="btn btn-sm btn-outline-primary ms-3 flex-shrink-0">';
            $html .= '<i class="bi bi-download me-1"></i>Download';
            $html .= '</a>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Formatteert een bestandsgrootte in bytes naar leesbaar formaat.
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Geeft een Bootstrap Icon naam terug op basis van MIME-type.
     */
    public static function getFileIcon(string $mimeType): string
    {
        if (strpos($mimeType, 'pdf') !== false) {
            return 'file-earmark-pdf';
        } elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) {
            return 'file-earmark-word';
        } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) {
            return 'file-earmark-excel';
        } elseif (strpos($mimeType, 'zip') !== false || strpos($mimeType, 'rar') !== false) {
            return 'file-earmark-zip';
        } elseif (strpos($mimeType, 'image') !== false) {
            return 'file-earmark-image';
        }
        return 'file-earmark-arrow-down';
    }
}

// Sidebar navigatie link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/file-library/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-file-earmark-arrow-down me-2"></i>Bestandsbibliotheek'
        . '</a></li>';
});

// Registreer shortcode [file_library]
add_shortcode('file_library', function ($atts) {
    return FileLibraryModule::renderList();
});

// Download handler: verwerk fl_download GET-parameter
add_action('init', function () {
    if (!isset($_GET['fl_download'])) {
        return;
    }

    $id = (int)$_GET['fl_download'];
    if ($id <= 0) {
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "file_library_files WHERE id = ? AND status = 'active'", [$id]);
    $file = $stmt->fetch();

    if (!$file) {
        return;
    }

    $filePath = UPLOADS_PATH . '/file-library/' . $file['filename'];

    if (!file_exists($filePath)) {
        return;
    }

    // Increment download count
    $db->query("UPDATE " . DB_PREFIX . "file_library_files SET download_count = download_count + 1 WHERE id = ?", [$id]);

    // Serve het bestand
    header('Content-Type: ' . ($file['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filePath);
    exit;
});
