<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();

header('Content-Type: application/json');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be signed in to manage bookmarks.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
if ($book_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid book was not supplied.'
    ]);
    exit;
}

$book_stmt = $conn->prepare("SELECT id, title FROM books WHERE id = ? LIMIT 1");
$book_stmt->bind_param("i", $book_id);
$book_stmt->execute();
$book_result = $book_stmt->get_result();
$book = $book_result->fetch_assoc();

if (!$book) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'The requested book could not be found.'
    ]);
    exit;
}

$exists_stmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND book_id = ? LIMIT 1");
$exists_stmt->bind_param("ii", $user_id, $book_id);
$exists_stmt->execute();
$exists_result = $exists_stmt->get_result();
$is_bookmarked = $exists_result->num_rows > 0;

if ($is_bookmarked) {
    $delete_stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND book_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $book_id);
    $delete_stmt->execute();
    $action = 'removed';
    $message = 'Removed from your saved books.';
    $type = 'warning';
} else {
    $insert_stmt = $conn->prepare("INSERT INTO bookmarks (user_id, book_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $book_id);
    $insert_stmt->execute();
    $action = 'added';
    $message = 'Saved to your bookmarks.';
    $type = 'success';
}

log_audit($conn, $action === 'added' ? 'bookmark_added' : 'bookmark_removed', 'bookmark', $book_id, [
    'book_id' => $book_id,
    'title' => $book['title']
]);

echo json_encode([
    'success' => true,
    'action' => $action,
    'is_bookmarked' => !$is_bookmarked,
    'message' => $message,
    'type' => $type,
    'title' => $book['title']
]);
