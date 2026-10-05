<?php

/**
 * admin/announcements.php
 * Master Admin Announcements Dashboard (Exact CRUD mappings)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

$errors = [];
$success = "";

// Dynamic edit and deletion pipeline handlers
$mode = $_GET['mode'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $start_at = $_POST['start_at'] ?? null;
    $end_at = $_POST['end_at'] ?? null;

    if ($action === 'create') {
        if (empty($title) || empty($message)) {
            $errors[] = "Title and message content cannot be left empty.";
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (title, message, is_active, start_at, end_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssiss", $title, $message, $is_active, $start_at, $end_at);
            if ($stmt->execute()) {
                log_audit($conn, "announcement_created", "announcements", $stmt->insert_id);
                $success = "Announcement successfully published.";
                $mode = 'list';
            } else {
                $errors[] = "Execution error: " . $stmt->error;
            }
        }
    } elseif ($action === 'update' && $edit_id > 0) {
        if (empty($title) || empty($message)) {
            $errors[] = "Title and message content cannot be left empty.";
        } else {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, message = ?, is_active = ?, start_at = ?, end_at = ? WHERE id = ?");
            $stmt->bind_param("ssissi", $title, $message, $is_active, $start_at, $end_at, $edit_id);
            if ($stmt->execute()) {
                log_audit($conn, "announcement_updated", "announcements", $edit_id);
                $success = "Announcement successfully updated.";
                $mode = 'list';
            } else {
                $errors[] = "Execution error: " . $stmt->error;
            }
        }
    } elseif ($action === 'delete') {
        $del_id = intval($_POST['delete_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) {
                log_audit($conn, "announcement_deleted", "announcements", $del_id);
                $success = "Announcement has been removed.";
            }
        }
    }
}

function render_announcements_content()
{
    global $conn, $BASE_URL, $mode, $edit_id, $errors, $success;

    // Display Alert Boxes
    if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif;

    if (!empty($errors)): ?>
        <div style="background: #fde8e8; color: #e11d48; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #fbd5d5;">
            <i class="ri-error-warning-fill"></i> <?= htmlspecialchars(implode(', ', $errors)) ?>
        </div>
    <?php endif;

    if ($mode === 'add' || $mode === 'edit') {
        $title_val = "";
        $message_val = "";
        $is_active_val = 1;
        $start_val = date('Y-m-d');
        $end_val = date('Y-m-d', strtotime('+7 days'));

        if ($mode === 'edit' && $edit_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            if ($data) {
                $title_val = $data['title'];
                $message_val = $data['message'];
                $is_active_val = $data['is_active'];
                $start_val = $data['start_at'] ? date('Y-m-d', strtotime($data['start_at'])) : '';
                $end_val = $data['end_at'] ? date('Y-m-d', strtotime($data['end_at'])) : '';
            }
        }
    ?>
        <div class="card-container">
            <h2 style="font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; margin: 0 0 24px;"><?= $mode === 'add' ? 'Create Announcement' : 'Edit Announcement' ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="<?= $mode === 'add' ? 'create' : 'update' ?>">

                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" value="<?= safe_output($title_val) ?>" class="form-control" required placeholder="e.g., Computer Science Registration Deadline">
                </div>

                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="message" class="form-control" style="height: 120px;" required placeholder="Enter message text..."><?= safe_output($message_val) ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Display From Date</label>
                        <input type="date" name="start_at" value="<?= $start_val ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="end_at" value="<?= $end_val ?>" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" id="isActiveCheck" <?= $is_active_val ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--primary-green);">
                    <label for="isActiveCheck" style="margin: 0; font-weight: 500;">Publish immediately and make visible to target audience</label>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Save Announcement</button>
                    <a href="?mode=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php
    } else {
        // List Mode
        $list = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
    ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">Create and publish digital bulletin board announcements.</p>
            <a href="?mode=add" class="btn btn-primary"><i class="ri-add-line"></i> Create Announcement</a>
        </div>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Start At</th>
                        <th>Expiry At</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="display: block; font-size: 0.92rem; color: var(--text-dark);"><?= safe_output($row['title']) ?></strong>
                                <span style="font-size: 0.78rem; color: var(--text-secondary); display: block; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= safe_output($row['message']) ?></span>
                            </td>
                            <td>
                                <?php if ($row['is_active']): ?>
                                    <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Active</span>
                                <?php else: ?>
                                    <span style="background: #f1f5f9; color: var(--text-secondary); padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 0.82rem; font-weight: 500;"><?= $row['start_at'] ? date('d M Y', strtotime($row['start_at'])) : 'N/A' ?></span></td>
                            <td><span style="font-size: 0.82rem; font-weight: 500;"><?= $row['end_at'] ? date('d M Y', strtotime($row['end_at'])) : 'N/A' ?></span></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <a href="?mode=edit&id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.78rem; border-radius: 6px;">
                                        Edit
                                    </a>
                                    <form method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('Delete this announcement permanently?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.78rem; border-radius: 6px;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($list->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 40px;">No announcements found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }
}

render_admin_layout('render_announcements_content', 'announcements', 'Bulletins & Announcements', ['Announcements' => '']);
?>