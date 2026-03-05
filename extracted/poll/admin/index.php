<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_poll') {
        $question = trim($_POST['question'] ?? '');
        $options  = array_filter(array_map('trim', $_POST['options'] ?? []));

        if (empty($question)) {
            $errors[] = 'De vraag is verplicht.';
        }
        if (count($options) < 2) {
            $errors[] = 'Vul minimaal 2 antwoordopties in.';
        }

        if (empty($errors)) {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "polls (question) VALUES (?)", [$question]);
            $pollId = (int)$db->lastInsertId();

            $sortOrder = 0;
            foreach ($options as $optionText) {
                $stmt = $db->query("INSERT INTO " . DB_PREFIX . "poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)", [$pollId, $optionText, $sortOrder++]);
            }

            flash('success', 'Poll succesvol aangemaakt. Gebruik shortcode [poll id="' . $pollId . '"] om de poll te tonen.');
            redirect(BASE_URL . '/modules/poll/admin/');
        }
    }

    if ($action === 'toggle_status') {
        $id      = (int)($_POST['id'] ?? 0);
        $current = $_POST['current_status'] ?? 'active';
        $new     = ($current === 'active') ? 'closed' : 'active';
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "polls SET status = ? WHERE id = ?", [$new, $id]);
            flash('success', 'Poll status bijgewerkt.');
            redirect(BASE_URL . '/modules/poll/admin/');
        }
    }

    if ($action === 'delete_poll') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "poll_votes WHERE poll_id = ?", [$id]);
            $db->query("DELETE FROM " . DB_PREFIX . "poll_options WHERE poll_id = ?", [$id]);
            $db->query("DELETE FROM " . DB_PREFIX . "polls WHERE id = ?", [$id]);
            flash('success', 'Poll verwijderd.');
            redirect(BASE_URL . '/modules/poll/admin/');
        }
    }
}

// View results for a specific poll
$viewResultsId = (int)($_GET['results'] ?? 0);
$pollResults   = null;
$pollOptions   = [];
if ($viewResultsId > 0) {
    $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "polls WHERE id = ?", [$viewResultsId]);
    $pollResults = $stmt->fetch();

    if ($pollResults) {
        $stmt = $db->query("
            SELECT o.*, COUNT(v.id) AS vote_count
            FROM " . DB_PREFIX . "poll_options o
            LEFT JOIN " . DB_PREFIX . "poll_votes v ON v.option_id = o.id
            WHERE o.poll_id = ?
            GROUP BY o.id
            ORDER BY o.sort_order ASC, o.id ASC
        ", [$viewResultsId]);
        $pollOptions = $stmt->fetchAll();

        $stmt = $db->query("SELECT COUNT(*) FROM " . DB_PREFIX . "poll_votes WHERE poll_id = ?", [$viewResultsId]);
        $totalVotes = (int)$stmt->fetchColumn();
    }
}

// Fetch all polls
$stmt = $db->query("
    SELECT p.*, COUNT(v.id) AS total_votes
    FROM " . DB_PREFIX . "polls p
    LEFT JOIN " . DB_PREFIX . "poll_votes v ON v.poll_id = p.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$polls = $stmt->fetchAll();

$pageTitle = 'Poll beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-bar-chart me-2"></i>Enquête / Poll</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPollModal">
            <i class="bi bi-plus-lg me-1"></i>Nieuwe poll
        </button>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <?php if ($pollResults): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Resultaten: <?= e($pollResults['question']) ?></h5>
                <a href="<?= BASE_URL ?>/modules/poll/admin/" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Terug
                </a>
            </div>
            <div class="card-body">
                <?php foreach ($pollOptions as $opt): ?>
                    <?php $pct = isset($totalVotes) && $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span><?= e($opt['option_text']) ?></span>
                            <span><?= $pct ?>% (<?= (int)$opt['vote_count'] ?> stem<?= $opt['vote_count'] != 1 ? 'men' : '' ?>)</span>
                        </div>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="text-muted mt-3">Totaal: <?= isset($totalVotes) ? $totalVotes : 0 ?> stemmen</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Vraag</th>
                        <th>Status</th>
                        <th>Stemmen</th>
                        <th>Shortcode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($polls)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Geen polls gevonden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($polls as $poll): ?>
                            <tr>
                                <td><?= (int)$poll['id'] ?></td>
                                <td><?= e(mb_substr($poll['question'], 0, 80)) ?><?= mb_strlen($poll['question']) > 80 ? '…' : '' ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$poll['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= e($poll['status']) ?>">
                                        <button type="submit" class="badge border-0 <?= $poll['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $poll['status'] === 'active' ? 'Actief' : 'Gesloten' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><?= (int)$poll['total_votes'] ?></td>
                                <td><code>[poll id="<?= (int)$poll['id'] ?>"]</code></td>
                                <td class="d-flex gap-1">
                                    <a href="?results=<?= (int)$poll['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-bar-chart"></i>
                                    </a>
                                    <form method="post" onsubmit="return confirm('Poll en alle stemmen verwijderen?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_poll">
                                        <input type="hidden" name="id" value="<?= (int)$poll['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Poll Modal -->
<div class="modal fade" id="addPollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="addPollForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_poll">
                <div class="modal-header">
                    <h5 class="modal-title">Nieuwe poll aanmaken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vraag <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Antwoordopties <span class="text-danger">*</span> <small class="text-muted">(min. 2, max. 6)</small></label>
                        <div id="poll-options-container">
                            <div class="input-group mb-2">
                                <input type="text" name="options[]" class="form-control" placeholder="Optie 1" required>
                            </div>
                            <div class="input-group mb-2">
                                <input type="text" name="options[]" class="form-control" placeholder="Optie 2" required>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addOptionBtn">
                            <i class="bi bi-plus me-1"></i>Optie toevoegen
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Poll opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var container = document.getElementById('poll-options-container');
    var addBtn    = document.getElementById('addOptionBtn');
    var maxOpts   = 6;

    if (addBtn && container) {
        addBtn.addEventListener('click', function () {
            var count = container.querySelectorAll('input').length;
            if (count >= maxOpts) {
                addBtn.disabled = true;
                return;
            }
            var div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = '<input type="text" name="options[]" class="form-control" placeholder="Optie ' + (count + 1) + '">' +
                '<button type="button" class="btn btn-outline-danger remove-option-btn"><i class="bi bi-x"></i></button>';
            div.querySelector('.remove-option-btn').addEventListener('click', function () {
                div.remove();
                addBtn.disabled = container.querySelectorAll('input').length >= maxOpts;
            });
            container.appendChild(div);
            if (container.querySelectorAll('input').length >= maxOpts) {
                addBtn.disabled = true;
            }
        });
    }
})();
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
