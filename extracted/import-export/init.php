<?php
/**
 * Import / Export Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class ImportExportModule
{
    // --- Export functies ---

    public static function exportPages(): void
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT id, title, slug, content, status, meta_title, meta_desc, created_at FROM " . DB_PREFIX . "pages ORDER BY id ASC"
        ) ?: [];

        self::outputCsv('pages_export_' . date('Ymd') . '.csv', $rows, [
            'id', 'title', 'slug', 'content', 'status', 'meta_title', 'meta_desc', 'created_at'
        ]);
    }

    public static function exportNews(): void
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT id, title, slug, excerpt, content, status, published_at, created_at FROM " . DB_PREFIX . "news ORDER BY id ASC"
        ) ?: [];

        self::outputCsv('news_export_' . date('Ymd') . '.csv', $rows, [
            'id', 'title', 'slug', 'excerpt', 'content', 'status', 'published_at', 'created_at'
        ]);
    }

    public static function exportUsers(): void
    {
        $db   = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT id, username, email, role, status, created_at FROM " . DB_PREFIX . "users ORDER BY id ASC"
        ) ?: [];

        self::outputCsv('users_export_' . date('Ymd') . '.csv', $rows, [
            'id', 'username', 'email', 'role', 'status', 'created_at'
        ]);
    }

    private static function outputCsv(string $filename, array $rows, array $headers): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        // BOM voor Excel UTF-8 herkenning
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit();
    }

    // --- Import functies ---

    /**
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public static function importPages(array $file): array
    {
        $db      = Database::getInstance();
        $handle  = self::openUpload($file);
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Leeg of ongeldig CSV-bestand.']];
        }

        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) { $skipped++; continue; }
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            $slug = trim($data['slug'] ?? '');
            if (!$slug) { $skipped++; continue; }

            $exists = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "pages WHERE slug = ?", [$slug]);
            if ($exists) { $skipped++; continue; }

            try {
                $db->insert(DB_PREFIX . 'pages', [
                    'title'      => $data['title'] ?? '',
                    'slug'       => $slug,
                    'content'    => $data['content'] ?? '',
                    'status'     => in_array($data['status'] ?? '', ['published', 'draft']) ? $data['status'] : 'draft',
                    'meta_title' => $data['meta_title'] ?? '',
                    'meta_desc'  => $data['meta_desc'] ?? '',
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = 'Fout bij slug "' . $slug . '": ' . $e->getMessage();
                $skipped++;
            }
        }

        fclose($handle);
        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public static function importNews(array $file): array
    {
        $db      = Database::getInstance();
        $handle  = self::openUpload($file);
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Leeg of ongeldig CSV-bestand.']];
        }

        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) { $skipped++; continue; }
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            $slug = trim($data['slug'] ?? '');
            if (!$slug) { $skipped++; continue; }

            $exists = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "news WHERE slug = ?", [$slug]);
            if ($exists) { $skipped++; continue; }

            try {
                $db->insert(DB_PREFIX . 'news', [
                    'title'        => $data['title'] ?? '',
                    'slug'         => $slug,
                    'excerpt'      => $data['excerpt'] ?? '',
                    'content'      => $data['content'] ?? '',
                    'status'       => in_array($data['status'] ?? '', ['published', 'draft']) ? $data['status'] : 'draft',
                    'published_at' => $data['published_at'] ?: null,
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = 'Fout bij slug "' . $slug . '": ' . $e->getMessage();
                $skipped++;
            }
        }

        fclose($handle);
        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private static function openUpload(array $file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload mislukt (code ' . $file['error'] . ').');
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Kon bestand niet openen.');
        }
        // Verwijder eventuele BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        return $handle;
    }
}

// Sidebar
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/import-export/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-arrow-down-up me-2"></i>Import / Export'
        . '</a></li>';
});
