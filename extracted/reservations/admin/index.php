<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_slot') {
        $slot_date = trim($_POST['slot_date'] ?? '');
        $slot_time = trim($_POST['slot_time'] ?? '');
        $max_reservations = (int)($_POST['max_reservations'] ?? 1);
        $title = trim($_POST['title'] ?? '');

        if (empty($slot_date) || empty($slot_time)) {
            $errors[] = 'Datum en tijd zijn verplicht.';
        }

        if (empty($errors)) {
            $stmt = $db->query("
                INSERT INTO " . DB_PREFIX . "reservation_slots (slot_date, slot_time, max_reservations, title)
                VALUES (?, ?, ?, ?)
            ", [$slot_date, $slot_time, $max_reservations, $title]);
            flash('success', 'Tijdslot toegevoegd.');
            redirect(BASE_URL . '/modules/reservations/admin/');
        }
    }

    if ($action === 'delete_slot') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "reservations WHERE slot_id = ?", [$id]);
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "reservation_slots WHERE id = ?", [$id]);
            flash('success', 'Tijdslot verwijderd.');
            redirect(BASE_URL . '/modules/reservations/admin/');
        }
    }

    if ($action === 'update_reservation_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if ($id > 0 && in_array($status, $allowed)) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "reservations SET status = ? WHERE id = ?", [$status, $id]);
            flash('success', 'Status bijgewerkt.');
            redirect(BASE_URL . '/modules/reservations/admin/');
        }
    }
}

// Fetch slots with reservation count
$stmt = $db->query("
    SELECT s.*,
           COUNT(r.id) AS reservation_count
    FROM " . DB_PREFIX . "reservation_slots s
    LEFT JOIN " . DB_PREFIX . "reservations r ON r.slot_id = s.id AND r.status != 'cancelled'
    GROUP BY s.id
    ORDER BY s.slot_date ASC, s.slot_time ASC
");
$slots = $stmt->fetchAll();

// Fetch all reservations with slot info
$stmt = $db->query("
    SELECT r.*, s.slot_date, s.slot_time, s.title AS slot_title
    FROM " . DB_PREFIX . "reservations r
    JOIN " . DB_PREFIX . "reservation_slots s ON s.id = r.slot_id
    ORDER BY s.slot_date ASC, s.slot_time ASC, r.created_at ASC
");
$reservations = $stmt->fetchAll();

$pageTitle = 'Reserveringen beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-calendar-check me-2"></i>Reserveringen</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal">
            <i class="bi bi-plus-lg me-1"></i>Nieuw tijdslot
        </button>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <div class="row g-4">
        <!-- Slots -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Tijdslots</h5></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Datum</th>
                                <th>Tijd</th>
                                <th>Naam</th>
                                <th>Bezetting</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($slots)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Geen tijdslots.</td></tr>
                            <?php else: ?>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><?= date('d-m-Y', strtotime($slot['slot_date'])) ?></td>
                                        <td><?= substr($slot['slot_time'], 0, 5) ?></td>
                                        <td><?= e($slot['title']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $slot['reservation_count'] >= $slot['max_reservations'] ? 'danger' : 'success' ?>">
                                                <?= (int)$slot['reservation_count'] ?>/<?= (int)$slot['max_reservations'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Tijdslot en reserveringen verwijderen?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_slot">
                                                <input type="hidden" name="id" value="<?= (int)$slot['id'] ?>">
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

        <!-- Reservations -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Reserveringen</h5></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Slot</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Geen reserveringen.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td><?= e($res['name']) ?></td>
                                        <td>
                                            <?= date('d-m-Y', strtotime($res['slot_date'])) ?>
                                            <?= substr($res['slot_time'], 0, 5) ?>
                                            <?php if (!empty($res['slot_title'])): ?>
                                                <br><small class="text-muted"><?= e($res['slot_title']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="mailto:<?= e($res['email']) ?>"><?= e($res['email']) ?></a></td>
                                        <td>
                                            <?php
                                            $statusMap = [
                                                'pending' => ['warning', 'In afwachting'],
                                                'confirmed' => ['success', 'Bevestigd'],
                                                'cancelled' => ['danger', 'Geannuleerd'],
                                            ];
                                            [$badge, $label] = $statusMap[$res['status']] ?? ['secondary', $res['status']];
                                            ?>
                                            <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
                                        </td>
                                        <td>
                                            <form method="post" class="d-flex gap-1">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_reservation_status">
                                                <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" style="width:auto">
                                                    <option value="pending" <?= $res['status'] === 'pending' ? 'selected' : '' ?>>In afwachting</option>
                                                    <option value="confirmed" <?= $res['status'] === 'confirmed' ? 'selected' : '' ?>>Bevestigd</option>
                                                    <option value="cancelled" <?= $res['status'] === 'cancelled' ? 'selected' : '' ?>>Geannuleerd</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-check"></i>
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
    </div>
</div>

<!-- Add Slot Modal -->
<div class="modal fade" id="addSlotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_slot">
                <div class="modal-header">
                    <h5 class="modal-title">Nieuw tijdslot toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Naam / omschrijving</label>
                        <input type="text" name="title" class="form-control" placeholder="bijv. Rondleiding ochtend">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum <span class="text-danger">*</span></label>
                        <input type="date" name="slot_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tijd <span class="text-danger">*</span></label>
                        <input type="time" name="slot_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max. reserveringen</label>
                        <input type="number" name="max_reservations" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Tijdslot opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
