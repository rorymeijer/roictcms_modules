<?php
/**
 * Contact Form Module — Main Boot File
 *
 * Geladen door ModuleManager::bootModules() voor elke actieve module.
 * Registreert hooks en definieert de [contact-form] shortcode functie.
 */

// ── Frontend: verwerk verzonden formulier ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_cf_action'] ?? '') === 'send') {
    ContactFormModule::handleSubmit();
}

// ── Hooks ─────────────────────────────────────────────────────────────────
// Laad de module CSS in <head>
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/contact-form/assets/css/form.css">' . PHP_EOL;
});

// Voeg sidebar-link toe in het admin paneel
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'contact-form' ? 'active' : '';
    echo '<a href="' . BASE_URL . '/modules/contact-form/admin/" class="nav-link ' . $isActive . '">'
       . '<i class="bi bi-envelope"></i> Contact Formulier</a>';
});

// ── Module class ──────────────────────────────────────────────────────────
class ContactFormModule
{
    // ── Render het HTML-formulier ──────────────────────────────────────────
    public static function renderForm(): string
    {
        $success = $_GET['cf_sent'] ?? false;
        $error   = $_SESSION['cf_error'] ?? null;
        unset($_SESSION['cf_error']);
        $old = $_SESSION['cf_old'] ?? [];
        unset($_SESSION['cf_old']);
        $successMsg = Settings::get('contact_form_success_msg', 'Bedankt voor uw bericht!');

        ob_start(); ?>
        <div class="cf-wrap" id="contact-form">
            <?php if ($success): ?>
            <div class="cf-success">
                <span class="cf-success-icon">✓</span>
                <p><?= e($successMsg) ?></p>
            </div>
            <?php else: ?>

            <?php if ($error): ?>
            <div class="cf-error-banner"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="cf-form" method="POST" action="<?= e($_SERVER['REQUEST_URI']) ?>#contact-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_cf_action" value="send">

                <?php if (Settings::get('contact_form_honeypot', '1')): ?>
                <!-- Honeypot — niet invullen -->
                <div class="cf-hp" aria-hidden="true">
                    <input type="text" name="cf_website" tabindex="-1" autocomplete="off">
                </div>
                <?php endif; ?>

                <div class="cf-row cf-row--2">
                    <div class="cf-field">
                        <label for="cf_name">Naam <span>*</span></label>
                        <input type="text" id="cf_name" name="cf_name"
                               value="<?= e($old['cf_name'] ?? '') ?>"
                               placeholder="Uw volledige naam" required maxlength="150">
                    </div>
                    <div class="cf-field">
                        <label for="cf_email">E-mailadres <span>*</span></label>
                        <input type="email" id="cf_email" name="cf_email"
                               value="<?= e($old['cf_email'] ?? '') ?>"
                               placeholder="u@voorbeeld.nl" required maxlength="150">
                    </div>
                </div>

                <div class="cf-field">
                    <label for="cf_subject">Onderwerp <span>*</span></label>
                    <input type="text" id="cf_subject" name="cf_subject"
                           value="<?= e($old['cf_subject'] ?? '') ?>"
                           placeholder="Waar gaat uw bericht over?" required maxlength="255">
                </div>

                <div class="cf-field">
                    <label for="cf_message">Bericht <span>*</span></label>
                    <textarea id="cf_message" name="cf_message"
                              rows="6" placeholder="Typ uw bericht hier..." required maxlength="5000"><?= e($old['cf_message'] ?? '') ?></textarea>
                </div>

                <div class="cf-submit">
                    <button type="submit" class="cf-btn">
                        <span>Versturen</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11Z"/>
                        </svg>
                    </button>
                    <p class="cf-privacy">Uw gegevens worden vertrouwelijk behandeld.</p>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Verwerk het ingediende formulier ──────────────────────────────────
    public static function handleSubmit(): void
    {
        // CSRF
        if (!csrf_verify()) {
            self::failBack('Beveiligingsfout. Probeer opnieuw.');
        }

        // Honeypot
        if (Settings::get('contact_form_honeypot', '1') && !empty($_POST['cf_website'])) {
            // Stil falen voor bots — doe alsof het gelukt is
            self::redirectSuccess();
        }

        // Rate limit: max N berichten per IP per uur
        $limit  = (int) Settings::get('contact_form_ratelimit', 5);
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db     = Database::getInstance();
        $recent = $db->fetch(
            "SELECT COUNT(*) as c FROM `" . DB_PREFIX . "contact_messages`
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        if ($recent && (int)$recent['c'] >= $limit) {
            self::failBack('U heeft te veel berichten verstuurd. Probeer het later opnieuw.');
        }

        // Validatie
        $name    = trim($_POST['cf_name']    ?? '');
        $email   = trim($_POST['cf_email']   ?? '');
        $subject = trim($_POST['cf_subject'] ?? '');
        $message = trim($_POST['cf_message'] ?? '');

        if (!$name)                        self::failBack('Vul uw naam in.', compact('name','email','subject','message'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) self::failBack('Vul een geldig e-mailadres in.', compact('name','email','subject','message'));
        if (!$subject)                     self::failBack('Vul een onderwerp in.', compact('name','email','subject','message'));
        if (strlen($message) < 10)         self::failBack('Uw bericht is te kort (minimaal 10 tekens).', compact('name','email','subject','message'));

        // Sla op in database
        $db->insert(DB_PREFIX . 'contact_messages', [
            'name'       => $name,
            'email'      => $email,
            'subject'    => $subject,
            'message'    => $message,
            'ip_address' => $ip,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'status'     => 'unread',
        ]);

        // Verstuur e-mail naar beheerder
        self::sendNotificationEmail($name, $email, $subject, $message);

        self::redirectSuccess();
    }

    // ── E-mail notificatie ────────────────────────────────────────────────
    private static function sendNotificationEmail(string $name, string $email, string $subject, string $message): void
    {
        $to       = Settings::get('contact_form_email', Settings::get('site_email', ''));
        $siteName = Settings::get('site_name', 'ROICT CMS');
        if (!$to) return;

        $mailSubject = str_replace('{site_name}', $siteName, Settings::get('contact_form_subject', 'Nieuw contactbericht'));

        $body = "Nieuw contactbericht ontvangen via {$siteName}\n";
        $body .= str_repeat('─', 50) . "\n\n";
        $body .= "Naam:       {$name}\n";
        $body .= "E-mail:     {$email}\n";
        $body .= "Onderwerp:  {$subject}\n\n";
        $body .= "Bericht:\n{$message}\n\n";
        $body .= str_repeat('─', 50) . "\n";
        $body .= "Ontvangen op: " . date('d-m-Y H:i:s') . "\n";
        $body .= "Beheer: " . BASE_URL . "/admin/modules/contact-form/\n";

        $headers  = "From: {$siteName} <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
        $headers .= "Reply-To: {$name} <{$email}>\r\n";
        $headers .= "X-Mailer: ROICT CMS Contact Form\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail($to, $mailSubject, $body, $headers);
    }

    private static function failBack(string $msg, array $old = []): never
    {
        $_SESSION['cf_error'] = $msg;
        if ($old) {
            $_SESSION['cf_old'] = [
                'cf_name'    => $old['name']    ?? '',
                'cf_email'   => $old['email']   ?? '',
                'cf_subject' => $old['subject'] ?? '',
                'cf_message' => $old['message'] ?? '',
            ];
        }
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        redirect($url . '#contact-form');
    }

    private static function redirectSuccess(): never
    {
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        redirect($url . '?cf_sent=1#contact-form');
    }
}

// ── Registreer shortcode-achtige helper ───────────────────────────────────
// Gebruik in een theme template: echo contact_form()
function contact_form(): string
{
    return ContactFormModule::renderForm();
}

// ── Registreer [contact_form] shortcode ───────────────────────────────────
// Gebruik in paginacontent: [contact_form]
add_shortcode('contact_form', 'contact_form');
