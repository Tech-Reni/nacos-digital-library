<?php
/**
 * approval/queue.php
 * Phase 7: Role Authorization (Governor/Admin Only)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Only governors and admins can access the approval queue
check_role(['governor', 'admin']);

$page_title = "Approval Queue";

// Fetch pending books (Phase 11: Prepared Statements)
$stmt = $conn->prepare("SELECT b.id, b.title, b.author, b.department, u.fullname as uploader 
                        FROM books b 
                        JOIN users u ON b.uploader_id = u.id 
                        WHERE b.status = 'pending' 
                        ORDER BY b.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Review and approve pending book uploads in the NACOS App moderation queue.">
    <title>NACOS App | Approval Queue</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
</head>
<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="container">
        <h1>Pending Approvals</h1>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Uploader</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($book = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= safe_output($book['title']) ?></td>
                        <td><?= safe_output($book['author']) ?></td>
                        <td><?= safe_output($book['uploader']) ?></td>
                        <td><?= safe_output(format_department($book['department'])) ?></td>
                        <td>
                            <a href="<?= $BASE_URL ?>approval/review.php?id=<?= $book['id'] ?>" class="btn btn-small">Review</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No pending approvals.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 0 18px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-gray); }
        .data-table th { background: var(--light-bg); color: var(--primary-green); font-weight: 600; }
        .btn-small { padding: 8px 12px; background: var(--primary-green); color: white; text-decoration: none; border-radius: 6px; font-size: 13px; }
    </style>
</body>
</html>
