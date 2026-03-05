<?php
// ── Runtime-migratie: voeg confirm_token toe als die ontbreekt ────────────────
try {
    Database::getInstance()->query(
        "ALTER TABLE `" . DB_PREFIX . "newsletter_subscribers`
         ADD COLUMN `confirm_token` VARCHAR(64) DEFAULT NULL"
    );
} catch (Exception $e) {}

// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'newsletter' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/newsletter/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-mailbox me-2"></i>Newsletter</a>';
});

// ── Laad module CSS in <head> ─────────────────────────────────────────────────
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/newsletter/assets/css/form.css">' . PHP_EOL;
});

// ── Module class ───────────────────────────────────────────────────────────────
class NewsletterModule
{
    // ── Render het inschrijfformulier ──────────────────────────────────────────
    public static function renderForm(): string
    {
        $pending  = isset($_GET['nl_pending']);
        $errCodes = [
            'csrf'           => 'Beveiligingsfout. Probeer opnieuw.',
            'invalid_email'  => 'Vul een geldig e-mailadres in.',
            'already_active' => 'Dit e-mailadres is al ingeschreven.',
        ];
        $errCode  = $_GET['nl_err'] ?? '';
        $error    = $errCodes[$errCode] ?? null;
        $oldEmail = $error ? urldecode($_GET['nl_em'] ?? '') : '';
        $oldName  = $error ? urldecode($_GET['nl_nm'] ?? '') : '';

        ob_start(); ?>
        <div class="nl-wrap" id="newsletter-form">
            <?php if ($pending): ?>
            <div class="nl-pending">
                <span class="nl-pending-icon">✉</span>
                <p>Controleer uw e-mail en klik op de bevestigingslink om uw inschrijving te voltooien.</p>
            </div>
            <?php else: ?>

            <?php if ($error): ?>
            <div class="nl-error-banner"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="nl-form" method="POST" action="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>#newsletter-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_nl_action" value="subscribe">

                <!-- Honeypot — niet invullen -->
                <div class="nl-hp" aria-hidden="true">
                    <input type="text" name="nl_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="nl-row nl-row--2">
                    <div class="nl-field">
                        <label for="nl_name">Naam</label>
                        <input type="text" id="nl_name" name="nl_name"
                               value="<?= e($oldName) ?>"
                               placeholder="Uw naam" maxlength="100">
                    </div>
                    <div class="nl-field">
                        <label for="nl_email">E-mailadres <span>*</span></label>
                        <input type="email" id="nl_email" name="nl_email"
                               value="<?= e($oldEmail) ?>"
                               placeholder="u@voorbeeld.nl" required maxlength="255">
                    </div>
                </div>

                <div class="nl-submit">
                    <button type="submit" class="nl-btn">
                        <span>Inschrijven</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>
                        </svg>
                    </button>
                    <p class="nl-privacy">Uw gegevens worden vertrouwelijk behandeld. U kunt zich altijd uitschrijven.</p>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Verwerk inschrijving (double opt-in) ───────────────────────────────────
    public static function handleSubscribe(): void
    {
        if (!csrf_verify()) {
            self::failBack('csrf');
            return;
        }

        // Honeypot
        if (!empty($_POST['nl_website'])) {
            self::redirectPending();
            return;
        }

        $email = trim($_POST['nl_email'] ?? '');
        $name  = trim($_POST['nl_name']  ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::failBack('invalid_email', $email, $name);
            return;
        }

        $db = Database::getInstance();

        $existing = $db->fetch(
            "SELECT id, status FROM `" . DB_PREFIX . "newsletter_subscribers` WHERE email = ?",
            [$email]
        );

        if ($existing) {
            if ($existing['status'] === 'active') {
                self::failBack('already_active', $email, $name);
                return;
            }

            // Heractiveer (uitgeschreven of pending): stuur nieuwe bevestigingsmail
            $confirmToken = bin2hex(random_bytes(32));
            $db->update(
                DB_PREFIX . 'newsletter_subscribers',
                ['status' => 'pending', 'name' => $name ?: null, 'confirm_token' => $confirmToken],
                'id = ?',
                [$existing['id']]
            );
            self::sendConfirmEmail($email, $name, $confirmToken);
        } else {
            $token        = bin2hex(random_bytes(32));
            $confirmToken = bin2hex(random_bytes(32));
            $db->insert(DB_PREFIX . 'newsletter_subscribers', [
                'email'         => $email,
                'name'          => $name ?: null,
                'status'        => 'pending',
                'token'         => $token,
                'confirm_token' => $confirmToken,
            ]);
            self::sendConfirmEmail($email, $name, $confirmToken);
        }

        self::redirectPending();
    }

    // ── Verstuur bevestigingsmail ──────────────────────────────────────────────
    public static function sendConfirmEmail(string $email, string $name, string $confirmToken): void
    {
        $confirmUrl = BASE_URL . '/?newsletter_confirm=' . $confirmToken;
        $siteName   = Settings::get('newsletter_from_name', Settings::get('site_name', 'Website'));
        $fromEmail  = Settings::get('newsletter_from_email', '');
        $from       = $siteName . ' <' . $fromEmail . '>';
        $greeting   = $name ? 'Beste ' . $name . ',' : 'Beste abonnee,';

        $subject = 'Bevestig uw nieuwsbriefinschrijving — ' . $siteName;

        $htmlBody = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:40px 0">
<table width="580" cellpadding="0" cellspacing="0" align="center" style="background:#fff;border-radius:8px;overflow:hidden">
<tr><td style="background:#2563eb;padding:28px 36px">
  <h1 style="color:#fff;margin:0;font-size:22px">' . htmlspecialchars($siteName) . '</h1>
</td></tr>
<tr><td style="padding:36px">
  <p style="font-size:15px;color:#333;line-height:1.6">' . htmlspecialchars($greeting) . '</p>
  <p style="font-size:15px;color:#333;line-height:1.6">Bedankt voor uw aanmelding voor onze nieuwsbrief. Klik op de knop hieronder om uw inschrijving te bevestigen:</p>
  <p style="text-align:center;margin:32px 0">
    <a href="' . $confirmUrl . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:bold;display:inline-block">Inschrijving bevestigen</a>
  </p>
  <p style="font-size:13px;color:#888;line-height:1.5">Of kopieer deze link naar uw browser:<br><a href="' . $confirmUrl . '" style="color:#2563eb">' . $confirmUrl . '</a></p>
  <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
  <p style="font-size:12px;color:#aaa">Heeft u zich niet aangemeld? Dan kunt u deze e-mail negeren.</p>
</td></tr>
</table>
</td></tr></table>
</body></html>';

        $headers = implode("\r\n", [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
        ]);

        @mail($email, $subject, $htmlBody, $headers);
    }

    private static function failBack(string $errCode, string $email = '', string $name = ''): void
    {
        $url    = strtok($_SERVER['REQUEST_URI'], '?');
        $params = 'nl_err=' . urlencode($errCode);
        if ($email !== '') $params .= '&nl_em=' . urlencode($email);
        if ($name  !== '') $params .= '&nl_nm=' . urlencode($name);
        redirect($url . '?' . $params . '#newsletter-form');
        exit;
    }

    private static function redirectPending(): void
    {
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        redirect($url . '?nl_pending=1#newsletter-form');
        exit;
    }
}

// ── Helper-functie voor gebruik in thema-templates ────────────────────────────
function newsletter_form(): string
{
    return NewsletterModule::renderForm();
}

// ── Registreer [newsletter] shortcode voor gebruik in CMS-paginacontent ───────
add_shortcode('newsletter', 'newsletter_form');

// ── Frontend: verwerk inschrijfformulier ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_nl_action'] ?? '') === 'subscribe') {
    NewsletterModule::handleSubscribe();
}

// ── Bevestigingsroute: /?newsletter_confirm=<token> ───────────────────────────
if (!empty($_GET['newsletter_confirm'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['newsletter_confirm']);
    if ($token) {
        $db  = Database::getInstance();
        $sub = $db->fetch(
            "SELECT id FROM `" . DB_PREFIX . "newsletter_subscribers`
             WHERE confirm_token = ? AND status = 'pending'",
            [$token]
        );
        if ($sub) {
            $db->update(
                DB_PREFIX . 'newsletter_subscribers',
                ['status' => 'active', 'confirm_token' => null],
                'id = ?',
                [$sub['id']]
            );
            die('<div style="font-family:sans-serif;text-align:center;margin-top:3rem">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:60px;height:60px;background:#22c55e;color:#fff;border-radius:50%;font-size:1.6rem;margin-bottom:1rem">✓</div>
                <h2 style="color:#166534">Inschrijving bevestigd!</h2>
                <p style="color:#555">U bent nu ingeschreven voor de nieuwsbrief.</p>
                <a href="' . BASE_URL . '" style="color:#2563eb">Terug naar de website</a>
            </div>');
        } else {
            die('<div style="font-family:sans-serif;text-align:center;margin-top:3rem">
                <h2 style="color:#991b1b">Ongeldige of verlopen link</h2>
                <p style="color:#555">De bevestigingslink is niet geldig of al gebruikt.</p>
                <a href="' . BASE_URL . '" style="color:#2563eb">Terug naar de website</a>
            </div>');
        }
    }
}

// ── Afmeld-route: /?newsletter_unsubscribe=<token> ────────────────────────────
if (!empty($_GET['newsletter_unsubscribe'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['newsletter_unsubscribe']);
    if ($token) {
        Database::getInstance()->update(
            DB_PREFIX . 'newsletter_subscribers',
            ['status' => 'unsubscribed'],
            'token = ?',
            [$token]
        );
    }
    die('<div style="font-family:sans-serif;text-align:center;margin-top:3rem">
        <p>Je bent succesvol uitgeschreven. <a href="' . BASE_URL . '">Terug naar de website</a></p>
    </div>');
}
