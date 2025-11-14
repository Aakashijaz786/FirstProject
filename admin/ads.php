<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_id = (int)($_POST['slot_id'] ?? 0);
    $ad_code = $_POST['ad_code'] ?? '';
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    if ($slot_id <= 0) {
        $message = 'Invalid ad slot.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE ads_slots SET ad_code=?, is_enabled=?, updated_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('sii', $ad_code, $is_enabled, $slot_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Advertisement slot updated.';
        } else {
            $message = 'Failed to update slot.';
            $message_type = 'danger';
        }
    }
}

$slots = [];
$res = $conn->query("SELECT * FROM ads_slots ORDER BY slot_key ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $slots[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <h4 class="mb-4">Advertisement Slots</h4>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($slots as $slot): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong><?php echo htmlspecialchars($slot['slot_name']); ?></strong>
                            <span class="d-block text-muted small"><?php echo htmlspecialchars($slot['description']); ?></span>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="slot_id" value="<?php echo (int)$slot['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Ad Code</label>
                                    <textarea class="form-control" name="ad_code" rows="5"><?php echo htmlspecialchars($slot['ad_code']); ?></textarea>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_enabled" <?php echo $slot['is_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Enabled</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save Slot</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
