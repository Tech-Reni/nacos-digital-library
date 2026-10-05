<?php
/**
 * approval/review.php
 * Phase 7: Review and approve/reject pending books
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Only governors and admins can review
check_role(['governor', 'admin']);

$book_id = intval($_GET['id'] ?? 0);

if ($book_id <= 0) {
    die("Invalid book ID.");
}

// Fetch book details with prepared statement
$stmt = $conn->prepare("SELECT b.*, u.fullname as uploader_name, bf.id as file_id, bf.storage_key, bf.mime, bf.pages
                        FROM books b
                        JOIN users u ON b.uploader_id = u.id
                        LEFT JOIN book_files bf ON b.id = bf.book_id
                        WHERE b.id = ? LIMIT 1");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Book not found.");
}

$book = $result->fetch_assoc();
$stmt->close();

$page_title = "Review: " . $book['title'];

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    
    if (in_array($action, ['approved', 'rejected', 'returned_for_edit'])) {
        // Update book status
        $update_stmt = $conn->prepare("UPDATE books SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $action, $book_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Log approval action
        log_audit($conn, "book_{$action}", "books", $book_id, [
            'book_title' => $book['title'],
            'comment' => $comment
        ]);
        
        // Redirect to queue
        header("Location: queue.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Review, approve, or reject pending book submissions in the NACOS App moderation system.">
    <title>NACOS App | Review Book</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="container">
        <div class="review-container">
            <div class="review-header">
                <h1>Review Book Submission</h1>
                <a href="queue.php" class="btn-back">
                    <i class="ri-arrow-left-line"></i> Back to Queue
                </a>
            </div>

            <div class="review-content">
                <div class="book-details">
                    <h2><?= safe_output($book['title']) ?></h2>
                    
                    <div class="detail-row">
                        <span class="label">Author:</span>
                        <span class="value"><?= safe_output($book['author'] ?? 'N/A') ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label">Uploaded By:</span>
                        <span class="value"><?= safe_output($book['uploader_name']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label">Department:</span>
                        <span class="value"><?= safe_output(ucwords(str_replace('-', ' ', $book['department']))) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label">Level:</span>
                        <span class="value"><?= safe_output($book['level'] ?? 'N/A') ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label">Pages:</span>
                        <span class="value"><?= safe_output($book['pages'] ?? 'Unknown') ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label">Uploaded:</span>
                        <span class="value"><?= safe_output(date('M d, Y', strtotime($book['created_at']))) ?></span>
                    </div>

                    <?php if (!empty($book['description'])): ?>
                        <div class="detail-row description">
                            <span class="label">Description:</span>
                            <p class="value"><?= safe_output($book['description']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pdf-preview">
                    <h3>Document Preview</h3>
                    <?php if ($book['storage_key'] && $book['id']): ?>
                        <?php
                        // Generate a valid access token for preview
                        $preview_token = bin2hex(random_bytes(32));
                        $preview_expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
                        $token_stmt = $conn->prepare("INSERT INTO pdf_access_tokens (token, book_file_id, user_id, expires_at) VALUES (?, ?, ?, ?)");
                        $token_stmt->bind_param("siis", $preview_token, $book['file_id'], $_SESSION['user_id'], $preview_expires);
                        $token_stmt->execute();
                        $token_stmt->close();
                        ?>
                        <iframe src="<?= $BASE_URL ?>view/serve_pdf.php?token=<?= $preview_token ?>" 
                                width="100%" height="600px" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        </iframe>
                    <?php else: ?>
                        <p class="no-preview">No preview available</p>
                    <?php endif; ?>
                </div>

                <form method="POST" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="form-group">
                        <label for="comment">Comment (optional):</label>
                        <textarea name="comment" id="comment" rows="3" 
                                  placeholder="Add a comment for the uploader..."><?= safe_output($_POST['comment'] ?? '') ?></textarea>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" name="action" value="approved" 
                                class="btn-approve" onclick="return confirm('Approve this book?')">
                            <i class="ri-check-line"></i> Approve
                        </button>
                        
                        <button type="submit" name="action" value="rejected" 
                                class="btn-reject" onclick="return confirm('Reject this book?')">
                            <i class="ri-close-line"></i> Reject
                        </button>
                        
                        <button type="submit" name="action" value="returned_for_edit" 
                                class="btn-return" onclick="return confirm('Return for edits?')">
                            <i class="ri-edit-line"></i> Return for Edit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 0 18px; }
        
        .review-container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .review-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--text-dark);
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f1f5f9;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            background: #e2e8f0;
        }
        
        .review-content {
            display: grid;
            gap: 30px;
        }
        
        .book-details {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .book-details h2 {
            margin: 0 0 20px 0;
            font-size: 1.5rem;
            color: var(--text-dark);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row .label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 150px;
            font-size: 0.9rem;
        }
        
        .detail-row .value {
            color: var(--text-dark);
            flex: 1;
        }
        
        .detail-row.description {
            flex-direction: column;
            gap: 8px;
        }
        
        .detail-row.description .value {
            line-height: 1.6;
            color: var(--text-secondary);
        }
        
        .pdf-preview {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .pdf-preview h3 {
            margin: 0 0 16px 0;
            font-size: 1.2rem;
            color: var(--text-dark);
        }
        
        .no-preview {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: white;
            border-radius: 8px;
            border: 2px dashed #e2e8f0;
        }
        
        .action-form {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            transition: all 0.2s ease;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .action-buttons button {
            flex: 1;
            min-width: 150px;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-approve {
            background: var(--primary-green, #0b8f3a);
            color: white;
        }
        
        .btn-approve:hover {
            background: var(--dark-green, #066b2a);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 143, 58, 0.3);
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-return {
            background: #f59e0b;
            color: white;
        }
        
        .btn-return:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons button {
                width: 100%;
            }
        }
    </style>
</body>
</html>