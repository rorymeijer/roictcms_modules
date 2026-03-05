<?php
defined('ROICT_CMS') or die('No direct access');

class WebhookManager
{
    public const AVAILABLE_EVENTS = [
        'page.created',
        'page.updated',
        'news.created',
        'news.updated',
        'user.created',
    ];

    public static function init(): void
    {
        add_action('admin_sidebar_nav', [self::class, 'sidebarNav']);
    }

    public static function sidebarNav(): void
    {
        $url = BASE_URL . '/modules/webhook-manager/admin/';
        echo '<a class="nav-link" href="' . $url . '">'
            . '<i class="bi bi-broadcast me-2"></i>Webhook Manager</a>';
    }

    /**
     * Trigger all active webhooks for a given event.
     *
     * @param string $event One of the AVAILABLE_EVENTS constants.
     * @param array  $data  Payload data to send with the webhook.
     */
    public static function trigger(string $event, array $data = []): void
    {
        $db = Database::getInstance();

        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "webhooks WHERE event = ? AND status = 'active'", [$event]);
        $webhooks = $stmt->fetchAll();

        if (empty($webhooks)) {
            return;
        }

        $payload = json_encode([
            'event'     => $event,
            'timestamp' => time(),
            'data'      => $data,
        ]);

        foreach ($webhooks as $webhook) {
            self::send($webhook, $event, $payload);
        }
    }

    private static function send(array $webhook, string $event, string $payload): void
    {
        $db = Database::getInstance();

        $headers = [
            'Content-Type: application/json',
            'User-Agent: ROICT-CMS-Webhook/1.0',
        ];

        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', $payload, $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($webhook['url'], false, $context);
        $responseCode = null;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $header, $matches)) {
                    $responseCode = (int)$matches[1];
                    break;
                }
            }
        }

        $db->query(
            "INSERT INTO " . DB_PREFIX . "webhook_logs
             (webhook_id, event, payload, response_code, response_body, sent_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $webhook['id'],
                $event,
                $payload,
                $responseCode,
                $responseBody !== false ? mb_strimwidth($responseBody, 0, 2000) : null,
            ]
        );
    }

    public static function sendTest(array $webhook): array
    {
        $payload = json_encode([
            'event'     => 'test',
            'timestamp' => time(),
            'data'      => ['message' => 'Dit is een testbericht van ROICT CMS Webhook Manager.'],
        ]);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: ROICT-CMS-Webhook/1.0',
        ];

        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', $payload, $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($webhook['url'], false, $context);
        $responseCode = null;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $header, $matches)) {
                    $responseCode = (int)$matches[1];
                    break;
                }
            }
        }

        $db = Database::getInstance();
        $db->query(
            "INSERT INTO " . DB_PREFIX . "webhook_logs
             (webhook_id, event, payload, response_code, response_body, sent_at)
             VALUES (?, 'test', ?, ?, ?, NOW())",
            [
                $webhook['id'],
                $payload,
                $responseCode,
                $responseBody !== false ? mb_strimwidth($responseBody, 0, 2000) : null,
            ]
        );

        return [
            'code' => $responseCode,
            'body' => $responseBody,
        ];
    }
}

WebhookManager::init();
