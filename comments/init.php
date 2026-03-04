<?php
/**
 * Reacties Module — Main Boot File
 *
 * Geladen door ModuleManager::bootModules() voor elke actieve module.
 * Registreert hooks en de [comments] shortcode.
 */

// ── Frontend: verwerk ingediend reactieformulier ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_cm_action'] ?? '') === 'post_comment') {
    CommentsModule::handleSubmit();
}

// ── Hooks ─────────────────────────────────────────────────────────────────
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/comments/assets/css/comments.css">' . PHP_EOL;
});

add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'comments' ? 'active' : '';
    $db = Database::getInstance();
    $pending = $db->fetch(
        "SELECT COUNT(*) c FROM `" . DB_PREFIX . "comments` WHERE status = 'pending'"
    )['c'] ?? 0;
    echo '<a href="' . BASE_URL . '/admin/modules/comments/" class="nav-link ' . $isActive . '">'
       . '<i class="bi bi-chat-dots"></i> Reacties'
       . ($pending > 0 ? ' <span style="background:#ef4444;color:white;border-radius:999px;padding:.05rem .45rem;font-size:.65rem;font-weight:700;">' . (int)$pending . '</span>' : '')
       . '</a>';
});

// ── Module class ───────────────────────────────────────────────────────────
class CommentsModule
{
    /**
     * Render het commentaarblok (lijst + formulier) voor een post/pagina.
     * Gebruik: echo comments_section('pagina-slug') of via [comments] shortcode.
     */
    public static function render(?string $postId = null): string
    {
        if ($postId === null) {
            // Gebruik de huidige URL-pad als post-identifier
            $postId = strtok($_SERVER['REQUEST_URI'], '?');
        }

        $db         = Database::getInstance();
        $moderation = (bool) Settings::get('comments_moderation', '1');
        $success    = isset($_GET['cm_sent']) && $_GET['cm_sent'] === '1'
                      && ($_GET['cm_post'] ?? '') === md5($postId);
        $error      = $_SESSION['cm_error'] ?? null;
        $old        = $_SESSION['cm_old'] ?? [];
        unset($_SESSION['cm_error'], $_SESSION['cm_old']);
        $successMsg = Settings::get('comments_success_msg', 'Bedankt voor uw reactie!');

        // Haal goedgekeurde reacties op
        $comments = $db->fetchAll(
            "SELECT * FROM `" . DB_PREFIX . "comments`
             WHERE post_id = ? AND status = 'approved'
             ORDER BY created_at ASC",
            [$postId]
        );

        ob_start(); ?>
        <div class="cm-wrap" id="comments">
            <h3 class="cm-title">
                <i class="cm-icon">💬</i>
                Reacties
                <?php if ($comments): ?>
                <span class="cm-count"><?= count($comments) ?></span>
                <?php endif; ?>
            </h3>

            <?php if ($comments): ?>
            <div class="cm-list">
                <?php foreach ($comments as $c): ?>
                <div class="cm-comment">
                    <div class="cm-avatar"><?= strtoupper(substr($c['author_name'], 0, 1)) ?></div>
                    <div class="cm-body">
                        <div class="cm-meta">
                            <span class="cm-author"><?= e($c['author_name']) ?></span>
                            <span class="cm-date"><?= date('d M Y \o\m H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div class="cm-text"><?= nl2br(e($c['content'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="cm-empty">Nog geen reacties. Wees de eerste!</p>
            <?php endif; ?>

            <div class="cm-form-wrap">
                <h4 class="cm-form-title">Plaats een reactie</h4>

                <?php if ($success): ?>
                <div class="cm-success">
                    <span class="cm-success-icon">✓</span>
                    <p><?= e($successMsg) ?></p>
                </div>
                <?php else: ?>

                <?php if ($error): ?>
                <div class="cm-error-banner"><?= e($error) ?></div>
                <?php endif; ?>

                <form class="cm-form" method="POST"
                      action="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>#comments"
                      novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_cm_action" value="post_comment">
                    <input type="hidden" name="_cm_post_id" value="<?= e($postId) ?>">

                    <?php if (Settings::get('comments_honeypot', '1')): ?>
                    <!-- Honeypot — niet invullen -->
                    <div class="cm-hp" aria-hidden="true">
                        <input type="text" name="cm_website" tabindex="-1" autocomplete="off">
                    </div>
                    <?php endif; ?>

                    <div class="cm-row">
                        <div class="cm-field">
                            <label for="cm_name">Naam <span>*</span></label>
                            <input type="text" id="cm_name" name="cm_name"
                                   value="<?= e($old['cm_name'] ?? '') ?>"
                                   placeholder="Uw naam" required maxlength="150">
                        </div>
                        <div class="cm-field">
                            <label for="cm_email">E-mailadres <span>*</span></label>
                            <input type="email" id="cm_email" name="cm_email"
                                   value="<?= e($old['cm_email'] ?? '') ?>"
                                   placeholder="u@voorbeeld.nl" required maxlength="150">
                            <small class="cm-hint">Wordt niet gepubliceerd.</small>
                        </div>
                    </div>

                    <div class="cm-field">
                        <label for="cm_content">Reactie <span>*</span></label>
                        <textarea id="cm_content" name="cm_content"
                                  rows="5" placeholder="Schrijf uw reactie hier..."
                                  required maxlength="3000"><?= e($old['cm_content'] ?? '') ?></textarea>
                    </div>

                    <div class="cm-submit">
                        <button type="submit" class="cm-btn">
                            <span>Reactie plaatsen</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11Z"/>
                            </svg>
                        </button>
                        <?php if ($moderation): ?>
                        <p class="cm-notice">Reacties worden beoordeeld voor publicatie.</p>
                        <?php endif; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Verwerk een ingediende reactie ─────────────────────────────────────
    public static function handleSubmit(): void
    {
        // CSRF
        if (!csrf_verify()) {
            self::failBack('Beveiligingsfout. Probeer opnieuw.');
        }

        // Honeypot
        if (Settings::get('comments_honeypot', '1') && !empty($_POST['cm_website'])) {
            self::redirectSuccess($_POST['_cm_post_id'] ?? '');
        }

        $postId = trim($_POST['_cm_post_id'] ?? '');
        if (!$postId) {
            self::failBack('Ongeldige aanvraag.');
        }

        // Rate limit: max N reacties per IP per uur
        $limit  = (int) Settings::get('comments_ratelimit', 3);
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db     = Database::getInstance();
        $recent = $db->fetch(
            "SELECT COUNT(*) c FROM `" . DB_PREFIX . "comments`
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        if ($recent && (int)$recent['c'] >= $limit) {
            self::failBack('U heeft te veel reacties geplaatst. Probeer het later opnieuw.');
        }

        // Validatie
        $name    = trim($_POST['cm_name']    ?? '');
        $email   = trim($_POST['cm_email']   ?? '');
        $content = trim($_POST['cm_content'] ?? '');

        if (!$name)                                          self::failBack('Vul uw naam in.',               compact('name', 'email', 'content'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))     self::failBack('Vul een geldig e-mailadres in.', compact('name', 'email', 'content'));
        if (strlen($content) < 5)                           self::failBack('Uw reactie is te kort (minimaal 5 tekens).', compact('name', 'email', 'content'));

        $moderation = (bool) Settings::get('comments_moderation', '1');
        $status     = $moderation ? 'pending' : 'approved';

        $db->insert(DB_PREFIX . 'comments', [
            'post_id'      => $postId,
            'author_name'  => $name,
            'author_email' => $email,
            'content'      => $content,
            'ip_address'   => $ip,
            'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'status'       => $status,
        ]);

        self::redirectSuccess($postId);
    }

    private static function failBack(string $msg, array $old = []): never
    {
        $_SESSION['cm_error'] = $msg;
        if ($old) {
            $_SESSION['cm_old'] = [
                'cm_name'    => $old['name']    ?? '',
                'cm_email'   => $old['email']   ?? '',
                'cm_content' => $old['content'] ?? '',
            ];
        }
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        redirect($url . '#comments');
    }

    private static function redirectSuccess(string $postId): never
    {
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        redirect($url . '?cm_sent=1&cm_post=' . urlencode(md5($postId)) . '#comments');
    }
}

// ── Helper-functie voor gebruik in thema templates ─────────────────────────
function comments_section(?string $postId = null): string
{
    return CommentsModule::render($postId);
}

// ── Shortcode [comments] ───────────────────────────────────────────────────
add_shortcode('comments', 'comments_section');
