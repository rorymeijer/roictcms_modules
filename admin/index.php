<?php
/**
 * Site Statistieken – admin/index.php
 * Multi-tab statistiekendashboard voor RoictCMS.
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db         = Database::getInstance();
$pageTitle  = 'Site Statistieken';
$activePage = 'site-statistics';
$tab        = $_GET['tab'] ?? 'dashboard';
$range      = in_array($_GET['range'] ?? '30', ['1','7','30','90','365']) ? (int)$_GET['range'] : 30;
$apiBase    = BASE_URL . '/modules/site-statistics/api/chart-data.php';
$trackApi   = BASE_URL . '/modules/site-statistics/api/track.php';

// ── POST-handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldig verzoek (CSRF).');
        redirect(BASE_URL . '/admin/?module=site-statistics&tab=' . $tab);
    }

    $postAction = $_POST['post_action'] ?? '';

    // Instellingen opslaan
    if ($postAction === 'save_settings') {
        Settings::setMultiple([
            'stats_enabled'         => isset($_POST['stats_enabled'])       ? '1' : '0',
            'stats_anonymize_ip'    => isset($_POST['stats_anonymize_ip'])  ? '1' : '0',
            'stats_respect_dnt'     => isset($_POST['stats_respect_dnt'])   ? '1' : '0',
            'stats_exclude_admin'   => isset($_POST['stats_exclude_admin']) ? '1' : '0',
            'stats_exclude_bots'    => isset($_POST['stats_exclude_bots'])  ? '1' : '0',
            'stats_require_consent' => isset($_POST['stats_require_consent']) ? '1' : '0',
            'stats_retention_days'  => max(30, min(3650, (int)($_POST['stats_retention_days'] ?? 395))),
            'stats_session_timeout' => max(60, min(86400, (int)($_POST['stats_session_timeout'] ?? 1800))),
            'stats_exclude_ips'     => substr(strip_tags($_POST['stats_exclude_ips'] ?? ''), 0, 2000),
        ]);
        flash('success', 'Instellingen opgeslagen.');
        redirect(BASE_URL . '/admin/?module=site-statistics&tab=settings');
    }

    // Verwijder data van specifieke bezoeker (AVG – recht op vergetelheid)
    if ($postAction === 'delete_visitor') {
        $term = trim($_POST['delete_term'] ?? '');
        if (strlen($term) < 3) {
            flash('error', 'Voer minimaal 3 tekens in.');
            redirect(BASE_URL . '/admin/?module=site-statistics&tab=privacy');
        }

        $pvDeleted = 0;
        $sesDeleted = 0;

        // Verwijder op IP-adres OF bezoekerhash
        $pvRows = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_pageviews` WHERE visitor_hash = ?",
            [$term]
        );
        $pvDeleted += (int)($pvRows['cnt'] ?? 0);
        $db->query("DELETE FROM `" . DB_PREFIX . "stats_pageviews` WHERE visitor_hash = ?", [$term]);

        $sesRows = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_sessions` WHERE visitor_hash = ? OR ip_address LIKE ?",
            [$term, '%'.$term.'%']
        );
        $sesDeleted += (int)($sesRows['cnt'] ?? 0);
        $db->query(
            "DELETE FROM `" . DB_PREFIX . "stats_sessions` WHERE visitor_hash = ? OR ip_address LIKE ?",
            [$term, '%'.$term.'%']
        );

        $total = $pvDeleted + $sesDeleted;

        // Sla op in verwijderingslog
        $currentUser = Auth::currentUser();
        $db->insert(DB_PREFIX . 'stats_deletions', [
            'deleted_by'      => $currentUser['username'] ?? 'admin',
            'criteria'        => 'visitor_hash/ip = ' . $term,
            'records_deleted' => $total,
            'deleted_at'      => date('Y-m-d H:i:s'),
        ]);

        flash('success', $total . ' record(s) verwijderd voor "' . e($term) . '".');
        redirect(BASE_URL . '/admin/?module=site-statistics&tab=privacy');
    }

    // Verwijder alle data tot een bepaalde datum
    if ($postAction === 'delete_before_date') {
        $date = $_POST['delete_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('error', 'Ongeldige datum.');
            redirect(BASE_URL . '/admin/?module=site-statistics&tab=privacy');
        }

        $pvCount  = $db->fetch("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_pageviews` WHERE DATE(created_at) < ?", [$date]);
        $sesCount = $db->fetch("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_sessions`   WHERE DATE(created_at) < ?", [$date]);
        $total    = (int)($pvCount['cnt'] ?? 0) + (int)($sesCount['cnt'] ?? 0);

        $db->query("DELETE FROM `" . DB_PREFIX . "stats_pageviews` WHERE DATE(created_at) < ?", [$date]);
        $db->query("DELETE FROM `" . DB_PREFIX . "stats_sessions`   WHERE DATE(created_at) < ?", [$date]);

        $currentUser = Auth::currentUser();
        $db->insert(DB_PREFIX . 'stats_deletions', [
            'deleted_by'      => $currentUser['username'] ?? 'admin',
            'criteria'        => 'alle data voor ' . $date,
            'records_deleted' => $total,
            'deleted_at'      => date('Y-m-d H:i:s'),
        ]);

        flash('success', $total . ' record(s) verwijderd vóór ' . e($date) . '.');
        redirect(BASE_URL . '/admin/?module=site-statistics&tab=privacy');
    }

    // Data exporteren als CSV
    if ($postAction === 'export_csv') {
        $exportFrom = $_POST['export_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $exportTo   = $_POST['export_to']   ?? date('Y-m-d');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="statistieken_' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM voor Excel
        fputcsv($out, ['datum', 'url', 'paginatitel', 'brontype', 'bron', 'zoekwoord'], ';');

        $rows = $db->fetchAll(
            "SELECT DATE(created_at) AS datum, url, page_title, referrer_type, referrer_source, search_keyword
             FROM `" . DB_PREFIX . "stats_pageviews`
             WHERE DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at ASC",
            [$exportFrom, $exportTo]
        );
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        fclose($out);
        exit;
    }
}

// ── Laad statistieken voor de huidige tab ─────────────────────────────────────
$totalPageviews = $db->fetch("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_pageviews`")['cnt'] ?? 0;
$totalSessions  = $db->fetch("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "stats_sessions` WHERE device_type != 'bot'")['cnt'] ?? 0;
$deletionLog    = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "stats_deletions` ORDER BY deleted_at DESC LIMIT 20");

$rangeLabel = match($range) {
    1   => 'Vandaag',
    7   => 'Afgelopen 7 dagen',
    30  => 'Afgelopen 30 dagen',
    90  => 'Afgelopen 90 dagen',
    365 => 'Afgelopen jaar',
    default => "Afgelopen {$range} dagen",
};

// ── Instellingen ophalen voor weergave ────────────────────────────────────────
$cfg = [
    'enabled'         => Settings::get('stats_enabled', '1'),
    'anonymize_ip'    => Settings::get('stats_anonymize_ip', '1'),
    'respect_dnt'     => Settings::get('stats_respect_dnt', '1'),
    'exclude_admin'   => Settings::get('stats_exclude_admin', '1'),
    'exclude_bots'    => Settings::get('stats_exclude_bots', '1'),
    'require_consent' => Settings::get('stats_require_consent', '0'),
    'retention_days'  => Settings::get('stats_retention_days', '395'),
    'session_timeout' => Settings::get('stats_session_timeout', '1800'),
    'exclude_ips'     => Settings::get('stats_exclude_ips', ''),
];

require_once ADMIN_PATH . '/includes/header.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-semibold">
                <i class="bi bi-bar-chart-line text-primary me-2"></i>Site Statistieken
            </h1>
            <small class="text-muted">Zelfgehoste analytics – AVG-compliant, geen externe koppelingen</small>
        </div>
        <!-- Datumbereik selector -->
        <div class="btn-group" role="group" aria-label="Datumbereik">
            <?php foreach ([1 => 'Vandaag', 7 => '7d', 30 => '30d', 90 => '90d', 365 => '1j'] as $d => $lbl): ?>
                <a href="?module=site-statistics&tab=<?= e($tab) ?>&range=<?= $d ?>"
                   class="btn btn-sm <?= $range === $d ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($lbl) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?= renderFlash() ?>

    <?php if (!$cfg['enabled']): ?>
        <div class="alert alert-warning">
            <i class="bi bi-pause-circle me-2"></i>
            Tracking is momenteel <strong>uitgeschakeld</strong>.
            <a href="?module=site-statistics&tab=settings" class="alert-link">Inschakelen in Instellingen</a>.
        </div>
    <?php endif; ?>

    <!-- Navigatietabs -->
    <ul class="nav nav-tabs mb-4" id="statsTabs">
        <?php
        $tabs = [
            'dashboard' => ['icon' => 'speedometer2',    'label' => 'Dashboard'],
            'realtime'  => ['icon' => 'circle-fill',     'label' => 'Realtime'],
            'pages'     => ['icon' => 'file-earmark',    'label' => "Pagina's"],
            'sources'   => ['icon' => 'signpost-split',  'label' => 'Bronnen'],
            'tech'      => ['icon' => 'display',         'label' => 'Technisch'],
            'privacy'   => ['icon' => 'shield-check',    'label' => 'AVG &amp; Privacy'],
            'settings'  => ['icon' => 'gear',            'label' => 'Instellingen'],
        ];
        foreach ($tabs as $key => $info):
            $active = ($tab === $key) ? 'active' : '';
        ?>
            <li class="nav-item">
                <a class="nav-link <?= $active ?>"
                   href="?module=site-statistics&tab=<?= $key ?>&range=<?= $range ?>">
                    <i class="bi bi-<?= $info['icon'] ?> me-1"></i><?= $info['label'] ?>
                    <?php if ($key === 'realtime'): ?>
                        <span class="badge bg-success ms-1 fs-tiny" id="realtimeBadge">●</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: DASHBOARD
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'dashboard'): ?>

    <!-- Samenvattingskaarten (geladen via JS/AJAX) -->
    <div class="row g-3 mb-4" id="summaryCards">
        <?php
        $cards = [
            ['id' => 'cardVisitors',   'icon' => 'people',        'label' => 'Bezoekers vandaag',    'color' => 'primary'],
            ['id' => 'cardPageviews',  'icon' => 'eye',           'label' => 'Paginaweergaven',       'color' => 'indigo'],
            ['id' => 'cardBounce',     'icon' => 'arrow-return-left','label' => 'Bouncepercentage',   'color' => 'warning'],
            ['id' => 'cardDuration',   'icon' => 'clock',         'label' => 'Gem. sessieduur',       'color' => 'success'],
            ['id' => 'cardNew',        'icon' => 'person-plus',   'label' => 'Nieuwe bezoekers',      'color' => 'info'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100 border-0 shadow-sm stats-summary-card" id="<?= $c['id'] ?>">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-muted small mb-1"><?= $c['label'] ?></div>
                            <div class="h3 mb-0 fw-bold stat-value">
                                <span class="placeholder col-4 bg-secondary rounded"></span>
                            </div>
                            <div class="small text-muted stat-delta mt-1"></div>
                        </div>
                        <div class="stats-icon-badge bg-<?= $c['color'] ?> bg-opacity-10 text-<?= $c['color'] ?>">
                            <i class="bi bi-<?= $c['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Hoofdgrafiek: tijdlijn -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-semibold">Bezoekers &amp; Paginaweergaven – <?= e($rangeLabel) ?></h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="chartStacked" onchange="toggleStacked()">
                            <label class="form-check-label small" for="chartStacked">Gestapeld</label>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <canvas id="timelineChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Verkeerbronnen</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="sourcesChart" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Uurverdeling + Top pagina's -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Verdeling per uur</h6>
                </div>
                <div class="card-body pt-2">
                    <canvas id="hourlyChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Top 10 Pagina's</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0" id="topPagesTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Pagina</th>
                                    <th class="text-end pe-3">Weergaven</th>
                                    <th class="text-end pe-3">Uniek</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center py-3 text-muted">Laden…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JSON voor JavaScript -->
    <script>
    var STATS_API   = <?= json_encode($apiBase) ?>;
    var STATS_RANGE = <?= (int)$range ?>;
    var STATS_TAB   = 'dashboard';
    </script>

    <?php endif; // dashboard ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: REALTIME
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'realtime'): ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm text-center py-4">
                <div class="display-2 fw-bold text-success" id="rtActiveCount">–</div>
                <div class="text-muted">actieve bezoekers<br><small>afgelopen 30 minuten</small></div>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Actieve pagina's (realtime)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="rtPagesTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Pagina</th>
                                    <th class="text-end pe-3">Bezoekers</th>
                                </tr>
                            </thead>
                            <tbody><tr><td colspan="2" class="text-center py-3 text-muted">Laden…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent pt-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Recente paginaweergaven</h6>
            <span class="badge bg-success">Live – ververst elke 30s</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="rtRecentTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Tijdstip</th>
                            <th>URL</th>
                            <th>Paginatitel</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="3" class="text-center py-3 text-muted">Laden…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    var STATS_API   = <?= json_encode($apiBase) ?>;
    var STATS_RANGE = <?= (int)$range ?>;
    var STATS_TAB   = 'realtime';
    </script>

    <?php endif; // realtime ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: PAGINA'S
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'pages'): ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent pt-3 pb-0">
            <h6 class="mb-0 fw-semibold">Top pagina's – <?= e($rangeLabel) ?></h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="allPagesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:40px">#</th>
                            <th>URL</th>
                            <th>Paginatitel</th>
                            <th class="text-end">Weergaven</th>
                            <th class="text-end">Unieke bez.</th>
                            <th class="text-end pe-3">Sessies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Laden…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    var STATS_API   = <?= json_encode($apiBase) ?>;
    var STATS_RANGE = <?= (int)$range ?>;
    var STATS_TAB   = 'pages';
    </script>

    <?php endif; // pages ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: BRONNEN
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'sources'): ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Verkeerbronnen</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="sourcesChartBig" style="max-height:300px"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Top verwijzende domeinen</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0" id="referrersTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Domein</th>
                                    <th>Type</th>
                                    <th class="text-end pe-3">Bezoeken</th>
                                </tr>
                            </thead>
                            <tbody><tr><td colspan="3" class="text-center py-3 text-muted">Laden…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent pt-3 pb-0">
            <h6 class="mb-0 fw-semibold">Zoekwoorden (van zoekmachines)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="keywordsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Zoekwoord</th>
                            <th class="text-end pe-3">Keren</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="2" class="text-center py-3 text-muted">Laden…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    var STATS_API   = <?= json_encode($apiBase) ?>;
    var STATS_RANGE = <?= (int)$range ?>;
    var STATS_TAB   = 'sources';
    </script>

    <?php endif; // sources ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: TECHNISCH
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'tech'): ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Apparaattype</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="devicesChart" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Browsers</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="browsersChart" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pt-3 pb-0">
                    <h6 class="mb-0 fw-semibold">Besturingssystemen</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="osChart" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent pt-3 pb-0">
            <h6 class="mb-0 fw-semibold">Browsertalen</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="languagesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Taal</th>
                            <th class="text-end pe-3">Sessies</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="2" class="text-center py-3 text-muted">Laden…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    var STATS_API   = <?= json_encode($apiBase) ?>;
    var STATS_RANGE = <?= (int)$range ?>;
    var STATS_TAB   = 'tech';
    </script>

    <?php endif; // tech ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: AVG & PRIVACY
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'privacy'): ?>

    <!-- Huidige privacystatus -->
    <div class="row g-3 mb-4">
        <?php
        $privChecks = [
            ['label' => 'IP-anonimisering',       'ok' => $cfg['anonymize_ip'],    'icon' => 'shield-lock'],
            ['label' => 'Do Not Track respect',   'ok' => $cfg['respect_dnt'],     'icon' => 'hand-thumbs-up'],
            ['label' => 'Bots uitgesloten',        'ok' => $cfg['exclude_bots'],    'icon' => 'robot'],
            ['label' => 'Toestemming vereist',     'ok' => $cfg['require_consent'], 'icon' => 'check-circle', 'neutral' => !$cfg['require_consent']],
        ];
        foreach ($privChecks as $pc):
            $color = $pc['ok'] ? 'success' : (isset($pc['neutral']) ? 'secondary' : 'danger');
            $icon  = $pc['ok'] ? 'check-circle-fill' : 'x-circle-fill';
        ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="bi bi-<?= $pc['icon'] ?> fs-2 text-<?= $color ?> mb-2"></i>
                <div class="fw-semibold small"><?= e($pc['label']) ?></div>
                <span class="badge bg-<?= $color ?> mt-1"><?= $pc['ok'] ? 'Aan' : 'Uit' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>AVG-informatie:</strong> Deze module slaat <em>geanonimiseerde</em> statistieken op.
            Bij ingeschakelde IP-anonimisering wordt het laatste octet van IPv4-adressen verwijderd.
            Bezoekerhashes roteren dagelijks en zijn niet herleidbaar tot personen.
            Bewaarperiode: <strong><?= e($cfg['retention_days']) ?> dagen</strong>.
        </div>
    </div>

    <div class="row g-4">

        <!-- Recht op vergetelheid -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent pt-3 pb-2">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-person-dash me-2 text-danger"></i>Recht op vergetelheid</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Verwijder alle statistieken van een specifieke bezoeker op basis van hun <em>bezoekerhash</em>
                        of IP-adres (ook geanonimiseerd). U kunt de hash opzoeken via het zoekformulier hieronder.
                    </p>
                    <!-- Zoek bezoeker -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bezoeker zoeken</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="avgSearchInput"
                                   placeholder="IP-adres of bezoekerhash (min. 3 tekens)">
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="avgSearch()">
                                <i class="bi bi-search"></i> Zoeken
                            </button>
                        </div>
                    </div>
                    <div id="avgSearchResults" class="mb-3"></div>

                    <!-- Verwijderformulier -->
                    <form method="post" onsubmit="return confirm('Zeker weten? Dit kan niet ongedaan worden gemaakt.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_action" value="delete_visitor">
                        <label class="form-label small fw-semibold">Bezoekerhash of IP om te verwijderen</label>
                        <div class="input-group">
                            <input type="text" name="delete_term" class="form-control form-control-sm"
                                   id="deleteTermInput" required minlength="3"
                                   placeholder="Voer hash of IP in">
                            <button class="btn btn-danger btn-sm" type="submit">
                                <i class="bi bi-trash me-1"></i>Verwijder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk verwijderen op datum -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent pt-3 pb-2">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-x me-2 text-warning"></i>Bulk verwijderen op datum</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Verwijder alle statistieken vóór een bepaalde datum. Handig voor het handmatig naleven
                        van de bewaarperiode of op verzoek van bezoekers.
                    </p>
                    <form method="post" onsubmit="return confirm('Alle data vóór de gekozen datum wordt permanent verwijderd. Doorgaan?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_action" value="delete_before_date">
                        <label class="form-label small fw-semibold">Verwijder alle data vóór</label>
                        <div class="input-group">
                            <input type="date" name="delete_date" class="form-control form-control-sm"
                                   value="<?= e(date('Y-m-d', strtotime('-' . $cfg['retention_days'] . ' days'))) ?>"
                                   max="<?= date('Y-m-d') ?>" required>
                            <button class="btn btn-warning btn-sm" type="submit">
                                <i class="bi bi-trash me-1"></i>Verwijder
                            </button>
                        </div>
                        <small class="text-muted">Huidige bewaarperiode: <?= e($cfg['retention_days']) ?> dagen</small>
                    </form>
                </div>
            </div>
        </div>

        <!-- Gegevensexport -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent pt-3 pb-2">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-download me-2 text-primary"></i>Gegevensexport (CSV)</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Exporteer geanonimiseerde statistieken als CSV-bestand. Geen persoonlijk identificeerbare
                        informatie (PII) wordt geëxporteerd.
                    </p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_action" value="export_csv">
                        <div class="row g-2 mb-3">
                            <div class="col">
                                <label class="form-label small">Van</label>
                                <input type="date" name="export_from" class="form-control form-control-sm"
                                       value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                            </div>
                            <div class="col">
                                <label class="form-label small">Tot en met</label>
                                <input type="date" name="export_to" class="form-control form-control-sm"
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-file-earmark-csv me-1"></i>Download CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Opt-out link -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent pt-3 pb-2">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-toggle-off me-2 text-secondary"></i>Opt-out voor bezoekers</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Voeg deze links toe aan uw privacyverklaring zodat bezoekers zich kunnen afmelden
                        voor statistieken (of weer aanmelden).
                    </p>
                    <label class="form-label small fw-semibold">Opt-out URL</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control form-control-sm font-monospace"
                               value="<?= e($trackApi) ?>?action=optout" readonly id="optoutUrl">
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyField('optoutUrl')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <label class="form-label small fw-semibold">Opt-in URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm font-monospace"
                               value="<?= e($trackApi) ?>?action=optin" readonly id="optinUrl">
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyField('optinUrl')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <div class="mt-3 p-2 bg-light rounded small font-monospace">
                        &lt;a href="<?= e($trackApi) ?>?action=optout"&gt;Afmelden voor statistieken&lt;/a&gt;
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <!-- Verwijderingslog -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent pt-3 pb-2">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2"></i>Verwijderingslog (AVG-audit)</h6>
        </div>
        <div class="card-body p-0">
            <?php if ($deletionLog): ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Datum</th>
                            <th>Uitvoerder</th>
                            <th>Criteria</th>
                            <th class="text-end pe-3">Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletionLog as $log): ?>
                        <tr>
                            <td class="ps-3 text-muted small"><?= e($log['deleted_at']) ?></td>
                            <td><?= e($log['deleted_by']) ?></td>
                            <td><code><?= e($log['criteria']) ?></code></td>
                            <td class="text-end pe-3"><?= e($log['records_deleted']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted text-center py-3 mb-0">Nog geen verwijderingen geregistreerd.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    var STATS_API = <?= json_encode($apiBase) ?>;
    var STATS_TAB = 'privacy';
    </script>

    <?php endif; // privacy ?>


    <!-- ════════════════════════════════════════════════════════════════════════
         TAB: INSTELLINGEN
    ════════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'settings'): ?>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="save_settings">

                <!-- Algemeen -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent pt-3 pb-2">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-toggles me-2"></i>Algemeen</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stats_enabled" id="stats_enabled"
                                   <?= $cfg['enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_enabled">
                                <strong>Tracking ingeschakeld</strong>
                                <div class="text-muted small">Uitschakelen stopt alle opname van bezoekersdata.</div>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stats_exclude_admin" id="stats_exclude_admin"
                                   <?= $cfg['exclude_admin'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_exclude_admin">
                                <strong>Beheerders niet tracken</strong>
                                <div class="text-muted small">Ingelogde beheerders worden uitgesloten van statistieken.</div>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="stats_exclude_bots" id="stats_exclude_bots"
                                   <?= $cfg['exclude_bots'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_exclude_bots">
                                <strong>Bots uitsluiten</strong>
                                <div class="text-muted small">Zoekmachines, crawlers en andere bots worden gefilterd.</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Privacy & AVG -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent pt-3 pb-2">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2"></i>Privacy &amp; AVG</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stats_anonymize_ip" id="stats_anonymize_ip"
                                   <?= $cfg['anonymize_ip'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_anonymize_ip">
                                <strong>IP-adressen anonimiseren</strong>
                                <div class="text-muted small">
                                    IPv4: laatste octet → 0 (bijv. 192.168.1.<s>100</s> → 192.168.1.0).<br>
                                    <span class="text-success"><i class="bi bi-check-circle"></i> Aanbevolen voor AVG-compliance.</span>
                                </div>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stats_respect_dnt" id="stats_respect_dnt"
                                   <?= $cfg['respect_dnt'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_respect_dnt">
                                <strong>Do Not Track header respecteren</strong>
                                <div class="text-muted small">Bezoekers met DNT=1 in hun browser worden niet gevolgd.</div>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stats_require_consent" id="stats_require_consent"
                                   <?= $cfg['require_consent'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stats_require_consent">
                                <strong>Toestemming vereisen vóór tracking</strong>
                                <div class="text-muted small">
                                    Alleen tracken als bezoeker <code>stats_consent=1</code> cookie heeft.
                                    Gebruik dit i.c.m. een cookiebanner.
                                </div>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Bewaartermijn (dagen)</label>
                                <input type="number" class="form-control form-control-sm" name="stats_retention_days"
                                       value="<?= e($cfg['retention_days']) ?>" min="30" max="3650">
                                <div class="form-text">AVG-richtlijn: maximaal 13 maanden (395 dagen).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Sessietime-out (seconden)</label>
                                <input type="number" class="form-control form-control-sm" name="stats_session_timeout"
                                       value="<?= e($cfg['session_timeout']) ?>" min="60" max="86400">
                                <div class="form-text">Standaard: 1800 s (30 min).</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP-uitsluiting -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent pt-3 pb-2">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-ban me-2"></i>IP-adressen uitsluiten</h6>
                    </div>
                    <div class="card-body">
                        <label class="form-label small">Één IP-adres per regel</label>
                        <textarea name="stats_exclude_ips" class="form-control form-control-sm font-monospace"
                                  rows="4" placeholder="bijv. 192.168.1.1"><?= e($cfg['exclude_ips']) ?></textarea>
                        <div class="form-text">Uw huidig IP-adres: <code><?= e($_SERVER['REMOTE_ADDR'] ?? '') ?></code></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Instellingen opslaan
                </button>
            </form>
        </div>

        <!-- Infokaart -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-database me-2"></i>Databasestatus</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-7 text-muted">Totale paginaweergaven</dt>
                        <dd class="col-sm-5 text-end fw-semibold"><?= number_format($totalPageviews) ?></dd>
                        <dt class="col-sm-7 text-muted">Totale sessies</dt>
                        <dd class="col-sm-5 text-end fw-semibold"><?= number_format($totalSessions) ?></dd>
                        <dt class="col-sm-7 text-muted">Bewaartermijn</dt>
                        <dd class="col-sm-5 text-end fw-semibold"><?= e($cfg['retention_days']) ?> d</dd>
                    </dl>
                </div>
            </div>
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body small">
                    <h6 class="fw-semibold"><i class="bi bi-lightbulb me-2 text-warning"></i>AVG-tips</h6>
                    <ul class="ps-3 mb-0">
                        <li class="mb-1">Activeer <strong>IP-anonimisering</strong> – verplicht bij opslaan van IP-adressen.</li>
                        <li class="mb-1">Zet <strong>DNT-respect</strong> aan als blijk van goede wil.</li>
                        <li class="mb-1">Vermeld het gebruik van statistieken in uw <strong>privacyverklaring</strong>.</li>
                        <li class="mb-1">Bied bezoekers een <strong>opt-out link</strong> aan (zie AVG-tab).</li>
                        <li>Hanteer een <strong>bewaartermijn</strong> van maximaal 13 maanden.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php endif; // settings ?>

</div><!-- /container -->

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
