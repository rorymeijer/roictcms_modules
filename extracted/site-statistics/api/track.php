<?php
/**
 * Site Statistieken – api/track.php
 * Tracking endpoint: verwerkt paginaweergaven en sessies.
 * AVG-compliant: IP-anonimisering, DNT-respect, opt-out.
 */

// Bootstrap CMS zonder sessie-overhead
define('STATS_TRACKING', true);
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex');

// ── Opt-out / Opt-in via GET ──────────────────────────────────────────────────
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'optout') {
        setcookie('stats_optout', '1', time() + (10 * 365 * 86400), '/', '', false, true);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
        exit;
    }
    if ($_GET['action'] === 'optin') {
        setcookie('stats_optout', '', time() - 3600, '/', '', false, true);
        setcookie('stats_consent', '1', time() + (365 * 86400), '/', '', false, true);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
        exit;
    }
}

// ── Noscript fallback (GET tracking pixel) ────────────────────────────────────
$isNoscript = isset($_GET['noscript']);

// ── Module ingeschakeld? ──────────────────────────────────────────────────────
if (!Settings::get('stats_enabled', '1')) {
    respond(['ok' => false, 'reason' => 'disabled']);
}

// ── Do Not Track ──────────────────────────────────────────────────────────────
if (Settings::get('stats_respect_dnt', '1') && ($_SERVER['HTTP_DNT'] ?? '') === '1') {
    respond(['ok' => false, 'reason' => 'dnt']);
}

// ── Opt-out cookie ────────────────────────────────────────────────────────────
if (!empty($_COOKIE['stats_optout'])) {
    respond(['ok' => false, 'reason' => 'optout']);
}

// ── Vereis toestemming indien ingesteld ───────────────────────────────────────
if (Settings::get('stats_require_consent', '0') && empty($_COOKIE['stats_consent'])) {
    respond(['ok' => false, 'reason' => 'no_consent']);
}

// ── IP ophalen en anonimiseren ────────────────────────────────────────────────
$rawIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip    = anonymizeIp($rawIp, (bool) Settings::get('stats_anonymize_ip', '1'));

// ── Uitgesloten IP-adressen ───────────────────────────────────────────────────
$excludedIps = array_filter(array_map('trim', explode("\n", Settings::get('stats_exclude_ips', ''))));
if ($excludedIps && in_array($rawIp, $excludedIps, true)) {
    respond(['ok' => false, 'reason' => 'excluded_ip']);
}

// ── User Agent ────────────────────────────────────────────────────────────────
$ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
$uaInfo = parseUserAgent($ua);

// ── Bots uitsluiten ───────────────────────────────────────────────────────────
if ($uaInfo['is_bot'] && Settings::get('stats_exclude_bots', '1')) {
    respond(['ok' => false, 'reason' => 'bot']);
}

// ── Input lezen ───────────────────────────────────────────────────────────────
if ($isNoscript) {
    $url      = $_GET['url'] ?? '/';
    $title    = '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $screen   = '';
    $language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 10);
    $clientSid = '';
} else {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['ok' => false, 'reason' => 'method']);
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        respond(['ok' => false, 'reason' => 'invalid_json']);
    }
    $url       = substr($input['url']      ?? '', 0, 512);
    $title     = substr($input['title']    ?? '', 0, 255);
    $referrer  = substr($input['referrer'] ?? '', 0, 512);
    $screen    = substr($input['screen']   ?? '', 0, 20);
    $language  = substr($input['lang']     ?? '', 0, 10);
    $clientSid = substr($input['sid']      ?? '', 0, 64);
}

if (empty($url)) {
    respond(['ok' => false, 'reason' => 'no_url']);
}

// ── Hash-salt & dagelijkse bezoekershash (AVG-vriendelijk, dagelijks roterend) ─
$salt        = Settings::get('stats_hash_salt', 'roict_stats');
$today       = date('Y-m-d');
$visitorHash = hash('sha256', $ip . $ua . $language . $today . $salt);

// Sessiehash: combinatie van bezoekerhash + client session ID + timewindow
$sessionTimeout = max(60, (int) Settings::get('stats_session_timeout', '1800'));
$timeWindow     = (int) floor(time() / $sessionTimeout);
$sessionHash    = hash('sha256', $visitorHash . ($clientSid ?: 'ns') . $timeWindow);

// ── Referrer classificeren ────────────────────────────────────────────────────
$refInfo = classifyReferrer($referrer);

// ── Database ──────────────────────────────────────────────────────────────────
$db = Database::getInstance();

// ── Sessie aanmaken of bijwerken ──────────────────────────────────────────────
$session = $db->fetch(
    "SELECT id, pages_count, created_at FROM `" . DB_PREFIX . "stats_sessions` WHERE session_hash = ? LIMIT 1",
    [$sessionHash]
);

if (!$session) {
    // Controleer of dit een nieuwe bezoeker is (eerste ooit, ook van andere dagen)
    $hasHistory = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "stats_sessions` WHERE visitor_hash = ? AND DATE(created_at) < ? LIMIT 1",
        [$visitorHash, $today]
    );

    $db->insert(DB_PREFIX . 'stats_sessions', [
        'session_hash'      => $sessionHash,
        'visitor_hash'      => $visitorHash,
        'ip_address'        => $ip,
        'device_type'       => $uaInfo['device'],
        'browser'           => $uaInfo['browser'],
        'os'                => $uaInfo['os'],
        'screen_resolution' => $screen,
        'language'          => $language,
        'is_new_visitor'    => $hasHistory ? 0 : 1,
        'pages_count'       => 1,
        'duration'          => 0,
        'is_bounce'         => 1,
        'created_at'        => date('Y-m-d H:i:s'),
        'last_activity'     => date('Y-m-d H:i:s'),
    ]);
} else {
    $duration   = (int) (time() - strtotime($session['created_at']));
    $pagesCount = (int) $session['pages_count'] + 1;
    $db->update(
        DB_PREFIX . 'stats_sessions',
        [
            'pages_count'   => $pagesCount,
            'duration'      => $duration,
            'is_bounce'     => 0,
            'last_activity' => date('Y-m-d H:i:s'),
        ],
        'session_hash = ?',
        [$sessionHash]
    );
}

// ── Paginaweergave opslaan ────────────────────────────────────────────────────
$db->insert(DB_PREFIX . 'stats_pageviews', [
    'visitor_hash'    => $visitorHash,
    'session_hash'    => $sessionHash,
    'url'             => $url,
    'page_title'      => $title,
    'referrer_url'    => $referrer,
    'referrer_type'   => $refInfo['type'],
    'referrer_source' => $refInfo['source'],
    'search_keyword'  => $refInfo['keyword'],
    'created_at'      => date('Y-m-d H:i:s'),
]);

// ── Periodieke opruiming (1% kans) ────────────────────────────────────────────
if (mt_rand(1, 100) === 1) {
    $retentionDays = max(30, (int) Settings::get('stats_retention_days', '395'));
    $db->query(
        "DELETE FROM `" . DB_PREFIX . "stats_pageviews` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$retentionDays]
    );
    $db->query(
        "DELETE FROM `" . DB_PREFIX . "stats_sessions` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$retentionDays]
    );
}

respond(['ok' => true]);

// ═════════════════════════════════════════════════════════════════════════════
// Helper-functies
// ═════════════════════════════════════════════════════════════════════════════

function respond(array $data): never
{
    echo json_encode($data);
    exit;
}

function anonymizeIp(string $ip, bool $doAnonymize): string
{
    if (!$doAnonymize) return $ip;

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4: vervang laatste octet door 0  (bijv. 192.168.1.100 → 192.168.1.0)
        return preg_replace('/\.\d+$/', '.0', $ip) ?? $ip;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6: vervang laatste 80 bits (5 groepen) door nullen
        $parts = explode(':', $ip);
        if (count($parts) >= 4) {
            $keep  = array_slice($parts, 0, 3);
            $zeros = array_fill(0, count($parts) - 3, '0');
            return implode(':', array_merge($keep, $zeros));
        }
    }
    return $ip;
}

function parseUserAgent(string $ua): array
{
    $ua_lower = strtolower($ua);

    // ── Bot-detectie ──────────────────────────────────────────────────────────
    $botPatterns = [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'curl',
        'wget', 'python-requests', 'go-http', 'java/', 'httpclient', 'axios',
        'pingdom', 'uptimerobot', 'monitor', 'checker', 'headlesschrome',
        'lighthouse', 'googlebot', 'bingbot', 'yandexbot', 'ahrefsbot',
        'semrushbot', 'duckduckbot', 'baiduspider', 'mj12bot', 'dotbot',
    ];
    foreach ($botPatterns as $p) {
        if (str_contains($ua_lower, $p)) {
            return ['device' => 'bot', 'browser' => 'Bot', 'os' => 'Bot', 'is_bot' => true];
        }
    }

    // ── Apparaattype ──────────────────────────────────────────────────────────
    $device = 'desktop';
    if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
        $device = 'tablet';
    } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone|opera mini|opera mobi/i', $ua)) {
        $device = 'mobile';
    }

    // ── Browser (volgorde belangrijk: specifiek eerst) ────────────────────────
    $browser = 'Overig';
    if (preg_match('/Edg\//i', $ua))           $browser = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/SamsungBrowser/i', $ua)) $browser = 'Samsung Internet';
    elseif (preg_match('/UCBrowser/i', $ua))   $browser = 'UC Browser';
    elseif (preg_match('/Chrome\/[\d.]+/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari\/[\d.]+/i', $ua))  $browser = 'Safari';
    elseif (preg_match('/MSIE|Trident\//i', $ua))  $browser = 'Internet Explorer';

    // ── Besturingssysteem ─────────────────────────────────────────────────────
    $os = 'Overig';
    if (preg_match('/Windows NT 10\.0/i', $ua))  $os = 'Windows 10/11';
    elseif (preg_match('/Windows NT/i', $ua))     $os = 'Windows';
    elseif (preg_match('/Mac OS X/i', $ua))       $os = 'macOS';
    elseif (preg_match('/Android\s[\d.]+/i', $ua, $m)) $os = 'Android';
    elseif (preg_match('/iPhone OS|iPad/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua))          $os = 'Linux';
    elseif (preg_match('/CrOS/i', $ua))           $os = 'Chrome OS';

    return ['device' => $device, 'browser' => $browser, 'os' => $os, 'is_bot' => false];
}

function classifyReferrer(string $referrer): array
{
    if (empty($referrer)) {
        return ['type' => 'direct', 'source' => 'Direct', 'keyword' => null];
    }

    $host    = strtolower(parse_url($referrer, PHP_URL_HOST) ?? '');
    $ownHost = strtolower(parse_url(BASE_URL, PHP_URL_HOST) ?? '');

    if ($host === $ownHost) {
        return ['type' => 'internal', 'source' => 'Intern', 'keyword' => null];
    }

    // Zoekmachines
    $searchEngines = [
        'google'     => 'Google',
        'bing'       => 'Bing',
        'yahoo'      => 'Yahoo',
        'duckduckgo' => 'DuckDuckGo',
        'ecosia'     => 'Ecosia',
        'startpage'  => 'Startpage',
        'baidu'      => 'Baidu',
        'yandex'     => 'Yandex',
        'ask.com'    => 'Ask',
    ];
    foreach ($searchEngines as $pattern => $name) {
        if (str_contains($host, $pattern)) {
            parse_str(parse_url($referrer, PHP_URL_QUERY) ?? '', $qs);
            $kw = $qs['q'] ?? $qs['query'] ?? $qs['p'] ?? $qs['text'] ?? null;
            return ['type' => 'search', 'source' => $name, 'keyword' => $kw ? substr($kw, 0, 255) : null];
        }
    }

    // Sociale netwerken
    $socials = [
        'facebook'  => 'Facebook',
        'twitter'   => 'Twitter/X',
        'x.com'     => 'Twitter/X',
        'instagram' => 'Instagram',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'pinterest' => 'Pinterest',
        'reddit'    => 'Reddit',
        'snapchat'  => 'Snapchat',
        'whatsapp'  => 'WhatsApp',
        't.me'      => 'Telegram',
    ];
    foreach ($socials as $pattern => $name) {
        if (str_contains($host, $pattern)) {
            return ['type' => 'social', 'source' => $name, 'keyword' => null];
        }
    }

    return ['type' => 'referral', 'source' => $host, 'keyword' => null];
}
