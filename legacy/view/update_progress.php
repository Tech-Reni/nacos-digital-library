<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

header('Content-Type: application/json');

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Verify user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$book_id = intval($_POST['book_id'] ?? 0);
$progress_percent = intval($_POST['progress_percent'] ?? 0);
$completed = isset($_POST['completed']) && $_POST['completed'] == '1';
$time_spent = intval($_POST['time_spent'] ?? 0);

if ($book_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid book ID']);
    exit;
}

try {
    // Update or insert reading history
    $stmt = $conn->prepare("
        INSERT INTO reading_history (user_id, book_id, progress_percent, last_opened) 
        VALUES (?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
        progress_percent = VALUES(progress_percent),
        last_opened = NOW()
    ");
    $stmt->bind_param("iii", $_SESSION['user_id'], $book_id, $progress_percent);
    $stmt->execute();
    $stmt->close();
    
    // If completed, log it
    if ($completed && $progress_percent >= 95) {
        log_audit($conn, "book_completed", "books", $book_id, [
            'progress' => $progress_percent,
            'time_spent' => $time_spent
        ]);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Progress updated',
        'progress' => $progress_percent
    ]);
    
} catch (Exception $e) {
    error_log("[Progress Update] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update progress']);
    exit;
}