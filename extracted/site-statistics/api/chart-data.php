<?php
/**
 * Site Statistieken – api/chart-data.php
 * AJAX endpoint voor Chart.js grafiekdata.
 * Alleen toegankelijk voor ingelogde beheerders.
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

header('Content-Type: application/json');
header('Cache-Control: no-store');

$db     = Database::getInstance();
$action = $_GET['action'] ?? '';
$range  = $_GET['range']  ?? '30'; // dagen
$range  = in_array($range, ['1', '7', '30', '90', '365'], true) ? (int) $range : 30;

switch ($action) {

    // ── Tijdlijn: bezoekers + paginaweergaven per dag ─────────────────────────
    case 'timeline':
        $rows = $db->fetchAll(
            "SELECT
                DATE(created_at)                    AS dag,
                COUNT(*)                            AS pageviews,
                COUNT(DISTINCT visitor_hash)        AS visitors
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY dag
             ORDER BY dag ASC",
            [$range]
        );

        $labels = [];
        $pvData = [];
        $visData = [];

        // Vul ontbrekende dagen op met 0
        $start = new DateTime("-{$range} days");
        $today = new DateTime();
        $interval = new DateInterval('P1D');
        $period   = new DatePeriod($start, $interval, (clone $today)->modify('+1 day'));

        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['dag']] = $r;
        }

        foreach ($period as $dt) {
            $d = $dt->format('Y-m-d');
            $labels[]  = $range <= 7 ? $dt->format('D d M') : ($range <= 30 ? $dt->format('d M') : $dt->format('M \'y'));
            $pvData[]  = (int) ($indexed[$d]['pageviews'] ?? 0);
            $visData[] = (int) ($indexed[$d]['visitors']  ?? 0);
        }

        echo json_encode([
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'Paginaweergaven', 'data' => $pvData,  'color' => '#4f46e5'],
                ['label' => 'Bezoekers',        'data' => $visData, 'color' => '#10b981'],
            ],
        ]);
        break;

    // ── Verkeerbronnen (donut) ────────────────────────────────────────────────
    case 'sources':
        $rows = $db->fetchAll(
            "SELECT referrer_type, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY referrer_type
             ORDER BY cnt DESC",
            [$range]
        );
        $labels = [];
        $data   = [];
        $labels_nl = [
            'direct'   => 'Direct',
            'search'   => 'Zoekmachines',
            'social'   => 'Sociaal',
            'referral' => 'Verwijzing',
            'internal' => 'Intern',
        ];
        foreach ($rows as $r) {
            $labels[] = $labels_nl[$r['referrer_type']] ?? $r['referrer_type'];
            $data[]   = (int) $r['cnt'];
        }
        echo json_encode(['labels' => $labels, 'data' => $data]);
        break;

    // ── Apparaten (donut) ─────────────────────────────────────────────────────
    case 'devices':
        $rows = $db->fetchAll(
            "SELECT device_type, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY device_type
             ORDER BY cnt DESC",
            [$range]
        );
        $labels = [];
        $data   = [];
        $labels_nl = [
            'desktop' => 'Desktop',
            'mobile'  => 'Mobiel',
            'tablet'  => 'Tablet',
            'unknown' => 'Onbekend',
        ];
        foreach ($rows as $r) {
            $labels[] = $labels_nl[$r['device_type']] ?? $r['device_type'];
            $data[]   = (int) $r['cnt'];
        }
        echo json_encode(['labels' => $labels, 'data' => $data]);
        break;

    // ── Browsers (donut) ─────────────────────────────────────────────────────
    case 'browsers':
        $rows = $db->fetchAll(
            "SELECT browser, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY browser
             ORDER BY cnt DESC
             LIMIT 8",
            [$range]
        );
        $labels = array_column($rows, 'browser');
        $data   = array_map('intval', array_column($rows, 'cnt'));
        echo json_encode(['labels' => $labels, 'data' => $data]);
        break;

    // ── Besturingssystemen (bar) ──────────────────────────────────────────────
    case 'os':
        $rows = $db->fetchAll(
            "SELECT os, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY os
             ORDER BY cnt DESC
             LIMIT 8",
            [$range]
        );
        $labels = array_column($rows, 'os');
        $data   = array_map('intval', array_column($rows, 'cnt'));
        echo json_encode(['labels' => $labels, 'data' => $data]);
        break;

    // ── Top pagina's ──────────────────────────────────────────────────────────
    case 'pages':
        $limit = min(50, max(5, (int) ($_GET['limit'] ?? 10)));
        $rows = $db->fetchAll(
            "SELECT
                url,
                MAX(page_title)              AS title,
                COUNT(*)                     AS views,
                COUNT(DISTINCT visitor_hash) AS unique_visitors,
                COUNT(DISTINCT session_hash) AS sessions
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY url
             ORDER BY views DESC
             LIMIT ?",
            [$range, $limit]
        );
        echo json_encode($rows);
        break;

    // ── Top verwijzende domeinen ──────────────────────────────────────────────
    case 'referrers':
        $rows = $db->fetchAll(
            "SELECT
                referrer_source AS source,
                referrer_type   AS type,
                COUNT(*)        AS cnt
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND referrer_type NOT IN ('direct','internal')
             GROUP BY referrer_source, referrer_type
             ORDER BY cnt DESC
             LIMIT 20",
            [$range]
        );
        echo json_encode($rows);
        break;

    // ── Zoekwoorden ───────────────────────────────────────────────────────────
    case 'keywords':
        $rows = $db->fetchAll(
            "SELECT search_keyword AS keyword, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND search_keyword IS NOT NULL
               AND search_keyword != ''
             GROUP BY search_keyword
             ORDER BY cnt DESC
             LIMIT 20",
            [$range]
        );
        echo json_encode($rows);
        break;

    // ── Samenvatting (vandaag + vergelijking gisteren) ────────────────────────
    case 'summary':
        $today = $db->fetch(
            "SELECT
                COUNT(*)                     AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors,
                COUNT(DISTINCT session_hash) AS sessions
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE DATE(created_at) = CURDATE()"
        );
        $yesterday = $db->fetch(
            "SELECT
                COUNT(*)                     AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors,
                COUNT(DISTINCT session_hash) AS sessions
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
        );
        $bounceRow = $db->fetch(
            "SELECT
                SUM(is_bounce) AS bounces,
                COUNT(*)       AS total
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE DATE(created_at) = CURDATE()
               AND device_type != 'bot'"
        );
        $durationRow = $db->fetch(
            "SELECT AVG(duration) AS avg_dur
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE DATE(created_at) = CURDATE()
               AND device_type != 'bot'
               AND duration > 0"
        );
        $newVisitors = $db->fetch(
            "SELECT COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE DATE(created_at) = CURDATE()
               AND is_new_visitor = 1
               AND device_type != 'bot'"
        );

        $bounceRate = ($bounceRow['total'] > 0)
            ? round(($bounceRow['bounces'] / $bounceRow['total']) * 100, 1)
            : 0;

        $avgDur = (int) ($durationRow['avg_dur'] ?? 0);

        echo json_encode([
            'today'       => $today,
            'yesterday'   => $yesterday,
            'bounce_rate' => $bounceRate,
            'avg_duration'=> $avgDur,
            'new_visitors'=> (int) ($newVisitors['cnt'] ?? 0),
        ]);
        break;

    // ── Realtime: actieve bezoekers (laatste 30 min) ──────────────────────────
    case 'realtime':
        // Gebruik PHP-tijd als grenswaarde zodat de tijdzone van de databaseserver
        // geen rol speelt (timestamps worden ook via PHP opgeslagen).
        $rtCutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));

        $active = $db->fetch(
            "SELECT COUNT(DISTINCT visitor_hash) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE last_activity >= ?",
            [$rtCutoff]
        );
        $recentPv = $db->fetchAll(
            "SELECT url, page_title, created_at
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$rtCutoff]
        );
        $perPage = $db->fetchAll(
            "SELECT url, page_title, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= ?
             GROUP BY url, page_title
             ORDER BY cnt DESC
             LIMIT 10",
            [$rtCutoff]
        );
        echo json_encode([
            'active'   => (int) ($active['cnt'] ?? 0),
            'recent'   => $recentPv,
            'per_page' => $perPage,
        ]);
        break;

    // ── Uurverdeling ──────────────────────────────────────────────────────────
    case 'hourly':
        $rows = $db->fetchAll(
            "SELECT HOUR(created_at) AS uur, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY uur
             ORDER BY uur ASC",
            [$range]
        );
        $data = array_fill(0, 24, 0);
        foreach ($rows as $r) {
            $data[(int) $r['uur']] = (int) $r['cnt'];
        }
        $labels = array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23));
        echo json_encode(['labels' => $labels, 'data' => $data]);
        break;

    // ── Talen ─────────────────────────────────────────────────────────────────
    case 'languages':
        $rows = $db->fetchAll(
            "SELECT language, COUNT(*) AS cnt
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
               AND language != ''
             GROUP BY language
             ORDER BY cnt DESC
             LIMIT 10",
            [$range]
        );
        echo json_encode($rows);
        break;

    // ── AVG: zoek bezoekersdata ───────────────────────────────────────────────
    case 'avg_search':
        Auth::requireAdmin();
        $term = trim($_GET['term'] ?? '');
        if (strlen($term) < 3) {
            echo json_encode(['error' => 'Zoekterm te kort']);
            break;
        }
        $rows = $db->fetchAll(
            "SELECT
                visitor_hash,
                ip_address,
                MIN(created_at) AS first_seen,
                MAX(last_activity) AS last_seen,
                COUNT(*) AS sessions,
                SUM(pages_count) AS pageviews
             FROM `" . DB_PREFIX . "stats_sessions`
             WHERE ip_address LIKE ? OR visitor_hash = ?
             GROUP BY visitor_hash, ip_address
             LIMIT 50",
            ['%' . $term . '%', $term]
        );
        echo json_encode($rows);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Onbekende actie']);
}
