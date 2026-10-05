<?php

/**
 * admin/books.php
 * Master Admin Books Directory View & Metadata Management
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

$errors = [];
$success = "";

// Dynamic deletion flow
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $book_id = intval($_POST['book_id'] ?? 0);

    if ($book_id > 0 && $action === 'delete') {
        // Fetch storage key references to unlink from upload targets
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

        // Remove DB references sequentially
        $del_files = $conn->prepare("DELETE FROM book_files WHERE book_id = ?");
        $del_files->bind_param("i", $book_id);
        $del_files->execute();

        $del_book = $conn->prepare("DELETE FROM books WHERE id = ?");
        $del_book->bind_param("i", $book_id);
        if ($del_book->execute()) {
            log_audit($conn, "book_deleted", "books", $book_id);
            $success = "Resource file assets and associated metadata cleared from library.";
        }
    }
}

function render_books_content()
{
    global $conn, $BASE_URL, $errors, $success;

    if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title & Author</th>
                    <th>department</th>
                    <th>Status</th>
                    <th>Submit Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT id, title, author, department, status, created_at FROM books ORDER BY created_at DESC");
                while ($book = $res->fetch_assoc()):
                ?>
                    <tr>
                        <td>
                            <strong style="display: block; font-size: 0.92rem; color: var(--text-dark);"><?= safe_output($book['title']) ?></strong>
                            <span style="font-size: 0.78rem; color: var(--text-secondary);">By <?= safe_output($book['author']) ?></span>
                        </td>
                        <td>
                            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);"><?= safe_output(format_department($book['department'])) ?></span>
                        </td>
                        <td>
                            <?php if ($book['status'] === 'approved'): ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Approved</span>
                            <?php elseif ($book['status'] === 'pending'): ?>
                                <span style="background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Pending</span>
                            <?php else: ?>
                                <span style="background: #fde8e8; color: #e11d48; padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700;">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size: 0.82rem; font-weight: 500; color: var(--text-secondary);"><?= date('d M Y', strtotime($book['created_at'])) ?></span>
                        </td>
                        <td style="text-align: right;">
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently clear this book and purge physical file assets? This action cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.78rem; border-radius: 6px;">
                                    Purge File
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($res->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 40px;">No books recorded in repository catalog.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

render_admin_layout('render_books_content', 'books', 'System Catalog Records', ['Books' => '']);
?>