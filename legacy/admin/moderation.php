<?php

/**
 * admin/moderation.php
 * Master Admin Book Moderation Pipeline
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

$errors = [];
$success = "";

// Form request execution logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $book_id = intval($_POST['book_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($book_id > 0) {
        if ($action === 'approve') {
            $update = $conn->prepare("UPDATE books SET status = 'approved' WHERE id = ?");
            $update->bind_param("i", $book_id);
            if ($update->execute()) {
                // Log state change in approvals table
                $log = $conn->prepare("INSERT INTO approvals (book_id, reviewer_id, action, comment) VALUES (?, ?, 'approved', ?)");
                $log->bind_param("iis", $book_id, $_SESSION['user_id'], $comment);
                $log->execute();

                log_audit($conn, "book_approved", "books", $book_id);
                $success = "Resource has been successfully approved.";
            }
        } elseif ($action === 'reject') {
            $update = $conn->prepare("UPDATE books SET status = 'rejected' WHERE id = ?");
            $update->bind_param("i", $book_id);
            if ($update->execute()) {
                $log = $conn->prepare("INSERT INTO approvals (book_id, reviewer_id, action, comment) VALUES (?, ?, 'rejected', ?)");
                $log->bind_param("iis", $book_id, $_SESSION['user_id'], $comment);
                $log->execute();

                log_audit($conn, "book_rejected", "books", $book_id);
                $success = "Resource submission has been rejected.";
            }
        } elseif ($action === 'delete') {
            // Find files associated with the book to clear storage spaces
            $file_stmt = $conn->prepare("SELECT storage_key FROM book_files WHERE book_id = ?");
            $file_stmt->bind_param("i", $book_id);
            $file_stmt->execute();
            $file_res = $file_stmt->get_result();
            while ($file = $file_res->fetch_assoc()) {
                $file_path = __DIR__ . '/../uploads/protected_books/' . $file['storage_key'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            $file_stmt->close();

            // Clear database records
            $del_files = $conn->prepare("DELETE FROM book_files WHERE book_id = ?");
            $del_files->bind_param("i", $book_id);
            $del_files->execute();

            $del_book = $conn->prepare("DELETE FROM books WHERE id = ?");
            $del_book->bind_param("i", $book_id);
            if ($del_book->execute()) {
                log_audit($conn, "book_deleted", "books", $book_id);
                $success = "Book record and associated protected storage files have been deleted.";
            }
        }
    }
}

function render_moderation_content()
{
    global $conn, $BASE_URL, $errors, $success;

    // Filters logic
    $dept = $_GET['department'] ?? 'all';
    $level = $_GET['level'] ?? 'all';
    $status = $_GET['status'] ?? 'pending';

    $sql = "SELECT b.id, b.uuid, b.title, b.author, b.department, b.level, b.status, u.fullname as uploader, b.created_at, bf.id as file_id, bf.storage_key, bf.mime
            FROM books b
            JOIN users u ON b.uploader_id = u.id
            LEFT JOIN book_files bf ON b.id = bf.book_id
            WHERE 1=1";

    $types = "";
    $params = [];

    if ($dept !== 'all') {
        $sql .= " AND b.department = ?";
        $params[] = $dept;
        $types .= "s";
    }
    if ($level !== 'all') {
        $sql .= " AND b.level = ?";
        $params[] = $level;
        $types .= "s";
    }
    if ($status !== 'all') {
        $sql .= " AND b.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
?>
    <?php if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="background: #fde8e8; color: #e11d48; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #fbd5d5;">
            <i class="ri-error-warning-fill"></i> <?= htmlspecialchars(implode(', ', $errors)) ?>
        </div>
    <?php endif; ?>

    <!-- Live Table Filter Controls Row -->
    <div class="card-container" style="padding: 16px 24px;">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary);">Department</label>
                <select name="department" class="form-control" style="width: 180px; padding: 8px 12px; height: 38px;" onchange="this.form.submit()">
                    <option value="all">All Departments</option>
                    <option value="computer-science" <?= $dept === 'computer-science' ? 'selected' : '' ?>>Computer Science</option>
                    <option value="mass-communication" <?= $dept === 'mass-communication' ? 'selected' : '' ?>>Mass Communication</option>
                    <option value="accountancy" <?= $dept === 'accountancy' ? 'selected' : '' ?>>Accountancy</option>
                </select>
            </div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary);">Academic Level</label>
                <select name="level" class="form-control" style="width: 140px; padding: 8px 12px; height: 38px;" onchange="this.form.submit()">
                    <option value="all">All Levels</option>
                    <option value="ND1" <?= $level === 'ND1' ? 'selected' : '' ?>>ND1</option>
                    <option value="ND2" <?= $level === 'ND2' ? 'selected' : '' ?>>ND2</option>
                    <option value="HND1" <?= $level === 'HND1' ? 'selected' : '' ?>>HND1</option>
                    <option value="HND2" <?= $level === 'HND2' ? 'selected' : '' ?>>HND2</option>
                </select>
            </div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary);">Status Flag</label>
                <select name="status" class="form-control" style="width: 140px; padding: 8px 12px; height: 38px;" onchange="this.form.submit()">
                    <option value="all">All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Submissions table wrapper -->
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Book Details</th>
                    <th>Uploader</th>
                    <th>Department & Level</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($book = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong style="display: block; font-size: 0.92rem; color: var(--text-dark);"><?= safe_output($book['title']) ?></strong>
                            <span style="font-size: 0.78rem; color: var(--text-secondary);">By <?= safe_output($book['author']) ?></span>
                        </td>
                        <td>
                            <span style="font-weight: 500; font-size: 0.85rem;"><?= safe_output($book['uploader']) ?></span>
                        </td>
                        <td>
                            <span style="display: block; font-size: 0.85rem; text-transform: uppercase; font-weight: 600;"><?= safe_output($book['level']) ?></span>
                            <span style="font-size: 0.78rem; color: var(--text-secondary);"><?= safe_output(format_department($book['department'])) ?></span>
                        </td>
                        <td>
                            <?php if ($book['status'] === 'pending'): ?>
                                <span style="background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Pending</span>
                            <?php elseif ($book['status'] === 'approved'): ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Approved</span>
                            <?php else: ?>
                                <span style="background: #fde8e8; color: #e11d48; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                                <?php if ($book['status'] === 'pending'): ?>
                                    <?php
                                    $preview_url = '';
                                    if (!empty($book['file_id'])) {
                                        $preview_token = bin2hex(random_bytes(32));
                                        $preview_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                                        $preview_stmt = $conn->prepare("INSERT INTO pdf_access_tokens (token, book_file_id, user_id, expires_at) VALUES (?, ?, ?, ?)");
                                        $preview_stmt->bind_param('siis', $preview_token, $book['file_id'], $_SESSION['user_id'], $preview_expires);
                                        if ($preview_stmt->execute()) {
                                            $preview_url = $BASE_URL . 'view/serve_preview.php?token=' . urlencode($preview_token);
                                        }
                                    }
                                    ?>
                                    <a href="<?= htmlspecialchars($preview_url ?: '#') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.78rem; height: 32px; border-radius: 6px;">
                                        <i class="ri-eye-line"></i> Preview
                                    </a>
                                    <form method="POST" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.78rem; height: 32px; border-radius: 6px;">
                                            Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.78rem; height: 32px; border-radius: 6px; background: #fee2e2; color: #ef4444;">
                                            Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this resource permanentely? This deletes storage assets!');">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.78rem; height: 32px; border-radius: 6px;">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 40px;">No moderation items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

render_admin_layout('render_moderation_content', 'moderation', 'Book Moderation Panel', ['Moderation' => '']);
?>