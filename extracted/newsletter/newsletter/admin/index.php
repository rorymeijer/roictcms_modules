<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Newsletter';
$activePage = 'newsletter';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Ongeldige aanvraag.'); redirect(BASE_URL . '/admin/modules/newsletter/'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_subscriber') {
        $db->delete(DB_PREFIX . 'newsletter_subscribers', 'id=?', [(int)$_POST['sub_id']]);
        flash('success', 'Abonnee verwijderd.');
    }

    if ($action === 'send_campaign') {
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body']    ?? '');
        $isHtml  = !empty($_POST['is_html']);

        if ($subject && $body) {
            $subs = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "newsletter_subscribers` WHERE status='active'");
            $fromName  = Settings::get('newsletter_from_name', '');
            $fromEmail = Settings::get('newsletter_from_email', '');
            $from      = $fromName . ' <' . $fromEmail . '>';
            $sent = 0;

            foreach ($subs as $sub) {
                $unsubUrl = BASE_URL . '/?newsletter_unsubscribe=' . $sub['token'];

                if ($isHtml) {
                    $footer    = '<p style="font-size:12px;color:#aaa;margin-top:30px;border-top:1px solid #eee;padding-top:12px;text-align:center">'
                               . 'U ontvangt deze mail omdat u zich heeft ingeschreven voor onze nieuwsbrief. '
                               . '<a href="' . $unsubUrl . '" style="color:#aaa">Uitschrijven</a></p>';
                    $bodyFinal = $body . $footer;
                    $headers   = implode("\r\n", [
                        'From: ' . $from,
                        'MIME-Version: 1.0',
                        'Content-Type: text/html; charset=utf-8',
                    ]);
                } else {
                    $bodyFinal = $body . "\n\n---\nUitschrijven: " . $unsubUrl;
                    $headers   = 'From: ' . $from . "\r\nContent-Type: text/plain; charset=utf-8";
                }

                if (@mail($sub['email'], $subject, $bodyFinal, $headers)) $sent++;
            }

            $db->insert(DB_PREFIX . 'newsletter_campaigns', [
                'subject'         => $subject,
                'body'            => $body,
                'is_html'         => $isHtml ? 1 : 0,
                'sent_at'         => date('Y-m-d H:i:s'),
                'recipient_count' => $sent,
            ]);
            flash('success', "Campagne verstuurd naar {$sent} abonnee(s).");
        }
    }

    if ($action === 'save_settings') {
        Settings::setMultiple([
            'newsletter_from_name'     => trim($_POST['from_name']      ?? ''),
            'newsletter_from_email'    => trim($_POST['from_email']     ?? ''),
            'newsletter_html_template' => $_POST['html_template']       ?? '',
        ]);
        flash('success', 'Instellingen opgeslagen.');
    }

    redirect(BASE_URL . '/admin/modules/newsletter/');
}

$tab         = $_GET['tab'] ?? 'subscribers';
$subscribers = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "newsletter_subscribers` ORDER BY subscribed_at DESC");
$campaigns   = $db->fetchAll("SELECT * FROM `" . DB_PREFIX . "newsletter_campaigns` ORDER BY created_at DESC");
$active      = count(array_filter($subscribers, fn($s) => $s['status'] === 'active'));
$pending     = count(array_filter($subscribers, fn($s) => $s['status'] === 'pending'));

$htmlTemplate = Settings::get('newsletter_html_template', '');

require_once ADMIN_PATH . '/includes/header.php';
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.ql-editor { min-height: 260px; font-size: 14px; }
.ql-toolbar.ql-snow { border-radius: 6px 6px 0 0; }
.ql-container.ql-snow { border-radius: 0 0 6px 6px; }
.ql-container.ql-source-active { border-radius: 0; }
.nl-type-toggle .btn { min-width: 110px; }

/* HTML-bronknop in toolbar */
.ql-source { font-weight: 700 !important; font-size: 11px !important; width: auto !important; padding: 0 7px !important; letter-spacing: -.5px; }
.ql-source.ql-active, .ql-source:hover { color: #2563eb !important; }

/* Source-textarea */
.nl-source-area {
    display: none;
    width: 100%;
    min-height: 260px;
    font-family: 'Courier New', monospace;
    font-size: 12.5px;
    line-height: 1.5;
    padding: 12px;
    border: 1px solid #ccc;
    border-top: none;
    border-radius: 0 0 6px 6px;
    resize: vertical;
    background: #1e1e2e;
    color: #cdd6f4;
    outline: none;
}
.nl-source-area:focus { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1><i class="bi bi-mailbox"></i> <?= e($pageTitle) ?></h1>
    <div class="d-flex gap-2">
        <span class="badge bg-primary fs-6"><?= $active ?> actief</span>
        <?php if ($pending > 0): ?>
        <span class="badge bg-warning text-dark fs-6"><?= $pending ?> in afwachting</span>
        <?php endif; ?>
    </div>
</div>
<?= renderFlash() ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'subscribers' ? 'active' : '' ?>" href="?tab=subscribers">Abonnees</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'campaigns'   ? 'active' : '' ?>" href="?tab=campaigns">Campagnes</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'settings'    ? 'active' : '' ?>" href="?tab=settings">Instellingen</a></li>
</ul>

<?php if ($tab === 'subscribers'): ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>E-mail</th><th>Naam</th><th>Status</th><th>Datum</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($subscribers as $s): ?>
                <tr>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['name'] ?: '—') ?></td>
                    <td>
                        <?php if ($s['status'] === 'active'): ?>
                            <span class="badge bg-success">actief</span>
                        <?php elseif ($s['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">wacht op bevestiging</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">uitgeschreven</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d-m-Y', strtotime($s['subscribed_at'])) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Verwijderen?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_subscriber">
                            <input type="hidden" name="sub_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($subscribers)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Geen abonnees.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'campaigns'): ?>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Nieuwe campagne versturen</strong></div>
            <div class="card-body">
                <form method="POST" id="campaign-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_campaign">
                    <input type="hidden" name="is_html" id="is_html_input" value="0">

                    <div class="mb-3">
                        <label class="form-label">Onderwerp</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>

                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0">Bericht</label>
                        <div class="btn-group btn-group-sm nl-type-toggle" role="group">
                            <button type="button" class="btn btn-outline-secondary active" id="btn-plain">
                                <i class="bi bi-type"></i> Platte tekst
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-html">
                                <i class="bi bi-code-slash"></i> HTML
                            </button>
                        </div>
                    </div>

                    <!-- Platte tekst -->
                    <div id="plain-editor" class="mb-3">
                        <textarea name="body" id="body-plain" class="form-control" rows="8"></textarea>
                    </div>

                    <!-- HTML WYSIWYG -->
                    <div id="html-editor" class="mb-3" style="display:none">
                        <div id="toolbar-campaign">
                            <span class="ql-formats">
                                <select class="ql-header"><option selected></option><option value="1"></option><option value="2"></option><option value="3"></option></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                                <button class="ql-strike"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-color"></select>
                                <select class="ql-background"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-align"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link"></button>
                                <button class="ql-image"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-clean"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-source" title="HTML-broncode bewerken">&lt;/&gt;</button>
                            </span>
                        </div>
                        <div id="quill-campaign" style="background:#fff"></div>
                        <textarea class="nl-source-area" id="source-campaign"></textarea>
                        <textarea name="body" id="body-html" style="display:none"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"
                            onclick="return confirm('Versturen naar alle actieve abonnees?')">
                        <i class="bi bi-send"></i> Versturen (<?= $active ?> ontvangers)
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Onderwerp</th><th>Type</th><th>Ontvangers</th><th>Verstuurd</th></tr></thead>
                    <tbody>
                        <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td><?= e($c['subject']) ?></td>
                            <td>
                                <?php if (!empty($c['is_html'])): ?>
                                    <span class="badge bg-info text-dark">HTML</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Tekst</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$c['recipient_count'] ?></td>
                            <td><?= $c['sent_at'] ? date('d-m-Y H:i', strtotime($c['sent_at'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($campaigns)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Nog geen campagnes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <label class="form-label">Afzendernaam</label>
                        <input type="text" name="from_name" class="form-control"
                               value="<?= e(Settings::get('newsletter_from_name', '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Afzender e-mailadres</label>
                        <input type="email" name="from_email" class="form-control"
                               value="<?= e(Settings::get('newsletter_from_email', '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Standaard HTML-sjabloon
                            <small class="text-muted fw-normal">(wordt voorgevuld bij nieuwe HTML-campagne)</small>
                        </label>
                        <div id="toolbar-template">
                            <span class="ql-formats">
                                <select class="ql-header"><option selected></option><option value="1"></option><option value="2"></option><option value="3"></option></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                                <button class="ql-strike"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-color"></select>
                                <select class="ql-background"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-align"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link"></button>
                                <button class="ql-image"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-clean"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-source" title="HTML-broncode bewerken">&lt;/&gt;</button>
                            </span>
                        </div>
                        <div id="quill-template" style="background:#fff"></div>
                        <textarea class="nl-source-area" id="source-template"></textarea>
                        <textarea name="html_template" id="template-input" style="display:none"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="save-settings-btn">
                        <i class="bi bi-save"></i> Opslaan
                    </button>
                </form>
                <hr>
                <p class="fw-semibold small mb-1">Shortcode voor paginacontent:</p>
                <pre class="bg-light p-2 rounded small mb-1"><code>[newsletter]</code></pre>
                <p class="fw-semibold small mb-1">Gebruik in thema-template (PHP):</p>
                <pre class="bg-light p-2 rounded small mb-0"><code>&lt;?= newsletter_form() ?&gt;</code></pre>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><strong>Voorvertoning sjabloon</strong></div>
            <div class="card-body p-0">
                <iframe id="template-preview" style="width:100%;height:420px;border:none;border-radius:0 0 6px 6px"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function () {
    var htmlTemplate = <?= json_encode($htmlTemplate) ?>;

    // ── Helper: source-toggle (WYSIWYG ↔ raw HTML) ────────────────────────────
    function makeSourceToggle(quill, sourceTextarea) {
        var sourceBtn = quill.container.previousElementSibling.querySelector('.ql-source');
        var container = quill.container; // .ql-container
        var inSource  = false;

        sourceBtn.addEventListener('click', function () {
            if (!inSource) {
                // WYSIWYG → source
                sourceTextarea.value = quill.root.innerHTML;
                container.style.display   = 'none';
                sourceTextarea.style.display = 'block';
                sourceBtn.classList.add('ql-active');
            } else {
                // source → WYSIWYG
                quill.clipboard.dangerouslyPasteHTML(sourceTextarea.value);
                sourceTextarea.style.display = 'none';
                container.style.display   = '';
                sourceBtn.classList.remove('ql-active');
            }
            inSource = !inSource;
        });

        // Geef de huidige HTML terug (werkt in beide modi)
        return function getHtml() {
            return inSource ? sourceTextarea.value : quill.root.innerHTML;
        };
    }

    <?php if ($tab === 'campaigns'): ?>
    // ── Campagne WYSIWYG ───────────────────────────────────────────────────────
    var quillCampaign = new Quill('#quill-campaign', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: '#toolbar-campaign',
                handlers: { source: function () {} } // afgehandeld door makeSourceToggle
            }
        }
    });
    var getCampaignHtml = makeSourceToggle(quillCampaign, document.getElementById('source-campaign'));

    var btnPlain    = document.getElementById('btn-plain');
    var btnHtml     = document.getElementById('btn-html');
    var plainWrap   = document.getElementById('plain-editor');
    var htmlWrap    = document.getElementById('html-editor');
    var isHtmlInput = document.getElementById('is_html_input');
    var bodyPlain   = document.getElementById('body-plain');
    var bodyHtml    = document.getElementById('body-html');
    var isHtmlMode  = false;

    function switchToHtml() {
        isHtmlMode = true;
        isHtmlInput.value = '1';
        plainWrap.style.display = 'none';
        htmlWrap.style.display  = '';
        bodyPlain.removeAttribute('required');
        btnPlain.classList.remove('active');
        btnHtml.classList.add('active');
        if (!quillCampaign.getText().trim()) {
            quillCampaign.clipboard.dangerouslyPasteHTML(htmlTemplate || '');
        }
    }

    function switchToPlain() {
        isHtmlMode = false;
        isHtmlInput.value = '0';
        plainWrap.style.display = '';
        htmlWrap.style.display  = 'none';
        bodyPlain.setAttribute('required', 'required');
        btnHtml.classList.remove('active');
        btnPlain.classList.add('active');
    }

    btnHtml.addEventListener('click', switchToHtml);
    btnPlain.addEventListener('click', switchToPlain);

    document.getElementById('campaign-form').addEventListener('submit', function () {
        if (isHtmlMode) {
            bodyHtml.value = getCampaignHtml();
            bodyHtml.setAttribute('name', 'body');
            bodyPlain.removeAttribute('name');
        } else {
            bodyPlain.setAttribute('name', 'body');
            bodyHtml.removeAttribute('name');
        }
    });
    <?php endif; ?>

    <?php if ($tab === 'settings'): ?>
    // ── Sjabloon WYSIWYG ───────────────────────────────────────────────────────
    var quillTemplate = new Quill('#quill-template', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: '#toolbar-template',
                handlers: { source: function () {} }
            }
        }
    });
    var getTemplateHtml = makeSourceToggle(quillTemplate, document.getElementById('source-template'));

    if (htmlTemplate) {
        quillTemplate.clipboard.dangerouslyPasteHTML(htmlTemplate);
    }

    // Live voorvertoning
    var preview = document.getElementById('template-preview');
    function updatePreview() {
        var doc = preview.contentDocument || preview.contentWindow.document;
        doc.open();
        doc.write(getTemplateHtml());
        doc.close();
    }
    quillTemplate.on('text-change', updatePreview);
    document.getElementById('source-template').addEventListener('input', updatePreview);
    updatePreview();

    document.getElementById('save-settings-btn').closest('form').addEventListener('submit', function () {
        document.getElementById('template-input').value = getTemplateHtml();
    });
    <?php endif; ?>
})();
</script>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
