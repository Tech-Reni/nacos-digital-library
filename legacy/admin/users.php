<?php
/**
 * admin/users.php
 * Master Admin Users Directory View & Operations Management
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

// Self-healing database check: Ensure 'status' column exists in users table
try {
    $conn->query("SELECT status FROM users LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        if ($action === 'suspend') {
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                log_audit($conn, "user_suspended", "users", $user_id);
                $success = "User account has been suspended.";
            }
        } elseif ($action === 'unsuspend') {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                log_audit($conn, "user_unsuspended", "users", $user_id);
                $success = "User account suspension has been lifted.";
            }
        } elseif ($action === 'promote_to_rep') {
            $stmt = $conn->prepare("UPDATE users SET role = 'course_rep' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                log_audit($conn, "user_promoted", "users", $user_id);
                $success = "User promoted to Course Representative privileges.";
            }
        } elseif ($action === 'demote') {
            $stmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                log_audit($conn, "user_demoted", "users", $user_id);
                $success = "User privileges demoted to default Student status.";
            }
        }
    }
}

function render_users_content() {
    global $conn, $BASE_URL, $errors, $success;

    if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-wrapper">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
            <a href="<?= $BASE_URL ?>admin/bulk_students.php" class="btn btn-primary" style="text-decoration: none; padding: 10px 16px; border-radius: 8px;"><i class="ri-file-upload-line"></i> Bulk Register Students</a>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Student Profile Details</th>
                    <th>Identity credentials</th>
                    <th>Role Privilege</th>
                    <th>Account State</th>
                    <th style="text-align: right;">Action Control</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $res = $conn->query("SELECT id, fullname, matric_number, department, level, role, status, last_login FROM users ORDER BY fullname ASC");
                while ($user = $res->fetch_assoc()): 
                ?>
                    <tr>
                        <td>
                            <strong style="display: block; font-size: 0.92rem; color: var(--text-dark);"><?= safe_output($user['fullname']) ?></strong>
                            <span style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;"><?= safe_output($user['level']) ?> • <?= safe_output(format_department($user['department'])) ?></span>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.88rem; font-weight: 600; color: var(--text-secondary);"><?= safe_output($user['matric_number']) ?></span>
                        </td>
                        <td>
                            <span style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.02em; color: var(--primary-green);"><?= safe_output($user['role']) ?></span>
                        </td>
                        <td>
                            <?php if (($user['status'] ?? 'active') === 'suspended'): ?>
                                <span style="background: #fde8e8; color: #e11d48; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Suspended</span>
                            <?php else: ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 8px;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    
                                    <?php if (($user['status'] ?? 'active') === 'suspended'): ?>
                                        <button type="submit" name="action" value="unsuspend" class="btn btn-small" style="background: var(--primary-green); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">Activate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="suspend" class="btn btn-small" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">Suspend</button>
                                    <?php endif; ?>

                                    <?php if ($user['role'] === 'student'): ?>
                                        <button type="submit" name="action" value="promote_to_rep" class="btn btn-small" style="background: #2563eb; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; margin-left: 4px;">Promote</button>
                                    <?php elseif ($user['role'] === 'course_rep'): ?>
                                        <button type="submit" name="action" value="demote" class="btn btn-small" style="background: #64748b; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; margin-left: 4px;">Demote</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php
}

render_admin_layout('render_users_content', 'users', 'Registered Student Directory', ['Users' => '']);
?>