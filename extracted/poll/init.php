<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class PollModule
{
    public static function renderPoll(int $pollId): string
    {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Fetch poll
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "polls WHERE id = ?", [$pollId]);
        $poll = $stmt->fetch();

        if (!$poll) {
            return '<p class="poll-empty text-muted">Poll niet gevonden.</p>';
        }

        // Fetch options
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "poll_options WHERE poll_id = ? ORDER BY sort_order ASC, id ASC", [$pollId]);
        $options = $stmt->fetchAll();

        if (empty($options)) {
            return '<p class="poll-empty text-muted">Deze poll heeft geen opties.</p>';
        }

        // Check if IP already voted
        $stmt = $db->query("SELECT option_id FROM " . DB_PREFIX . "poll_votes WHERE poll_id = ? AND ip_address = ? LIMIT 1", [$pollId, $ip]);
        $existingVote = $stmt->fetch();

        $hasVoted = ($existingVote !== false) || ($poll['status'] === 'closed');

        // Total votes
        $stmt = $db->query("SELECT COUNT(*) FROM " . DB_PREFIX . "poll_votes WHERE poll_id = ?", [$pollId]);
        $totalVotes = (int)$stmt->fetchColumn();

        $html = '<div class="poll-widget" id="poll-' . $pollId . '">';
        $html .= '<div class="poll-question">' . e($poll['question']) . '</div>';

        if ($poll['status'] === 'closed') {
            $html .= '<div class="badge bg-secondary mb-2">Gesloten</div>';
        }

        if ($hasVoted) {
            // Show results as bar chart
            $html .= '<div class="poll-results">';
            foreach ($options as $option) {
                $optId = (int)$option['id'];
                $stmt = $db->query("SELECT COUNT(*) FROM " . DB_PREFIX . "poll_votes WHERE option_id = ?", [$optId]);
                $votes = (int)$stmt->fetchColumn();
                $pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
                $isMyVote = isset($existingVote['option_id']) && (int)$existingVote['option_id'] === $optId;

                $html .= '<div class="poll-result-item' . ($isMyVote ? ' poll-result-myvote' : '') . '">';
                $html .= '<div class="d-flex justify-content-between mb-1">';
                $html .= '<span class="poll-option-label">' . e($option['option_text']) . ($isMyVote ? ' <i class="bi bi-check-circle-fill text-primary"></i>' : '') . '</span>';
                $html .= '<span class="poll-option-pct">' . $pct . '%</span>';
                $html .= '</div>';
                $html .= '<div class="progress poll-progress">';
                $html .= '<div class="progress-bar' . ($isMyVote ? ' bg-primary' : ' bg-secondary') . '" role="progressbar" style="width:' . $pct . '%" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"></div>';
                $html .= '</div>';
                $html .= '<small class="text-muted">' . $votes . ' stem' . ($votes !== 1 ? 'men' : '') . '</small>';
                $html .= '</div>';
            }
            $html .= '<p class="poll-total mt-2 text-muted"><small>Totaal: ' . $totalVotes . ' stem' . ($totalVotes !== 1 ? 'men' : '') . '</small></p>';
            $html .= '</div>';
        } else {
            // Show voting form
            $html .= '<form method="post" class="poll-form">';
            $html .= csrf_field();
            $html .= '<input type="hidden" name="_action" value="_poll_vote">';
            $html .= '<input type="hidden" name="poll_id" value="' . $pollId . '">';
            $html .= '<div class="poll-options mb-3">';
            foreach ($options as $option) {
                $optId = (int)$option['id'];
                $html .= '<div class="form-check poll-option-item">';
                $html .= '<input class="form-check-input" type="radio" name="option_id" id="poll-opt-' . $optId . '" value="' . $optId . '" required>';
                $html .= '<label class="form-check-label" for="poll-opt-' . $optId . '">' . e($option['option_text']) . '</label>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '<button type="submit" class="btn btn-primary btn-sm">Stem uitbrengen</button>';
            $html .= '</form>';
        }

        $html .= '</div>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/poll/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-bar-chart me-2"></i>Enquête / Poll</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/poll/assets/css/poll.css">';
});

// Register shortcode: [poll id="1"]
add_shortcode('poll', function ($atts) {
    $pollId = (int)($atts['id'] ?? 0);
    if ($pollId <= 0) {
        return '<p class="text-muted">Geen geldig poll ID opgegeven.</p>';
    }
    return PollModule::renderPoll($pollId);
});

// POST handler for votes
add_action('init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === '_poll_vote') {
        csrf_verify();

        $pollId   = (int)($_POST['poll_id'] ?? 0);
        $optionId = (int)($_POST['option_id'] ?? 0);
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($pollId > 0 && $optionId > 0) {
            $db = Database::getInstance();

            // Check poll exists and is active
            $stmt = $db->query("SELECT id FROM " . DB_PREFIX . "polls WHERE id = ? AND status = 'active'", [$pollId]);
            $pollExists = $stmt->fetch();

            if ($pollExists) {
                // Check option belongs to poll
                $stmt = $db->query("SELECT id FROM " . DB_PREFIX . "poll_options WHERE id = ? AND poll_id = ?", [$optionId, $pollId]);
                $optionExists = $stmt->fetch();

                if ($optionExists) {
                    // Check if already voted
                    $stmt = $db->query("SELECT id FROM " . DB_PREFIX . "poll_votes WHERE poll_id = ? AND ip_address = ?", [$pollId, $ip]);
                    $alreadyVoted = $stmt->fetch();

                    if (!$alreadyVoted) {
                        $stmt = $db->query("INSERT INTO " . DB_PREFIX . "poll_votes (poll_id, option_id, ip_address) VALUES (?, ?, ?)", [$pollId, $optionId, $ip]);
                        flash('success', 'Uw stem is uitgebracht.');
                    } else {
                        flash('info', 'U heeft al gestemd op deze poll.');
                    }
                }
            }
        }

        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }
});
