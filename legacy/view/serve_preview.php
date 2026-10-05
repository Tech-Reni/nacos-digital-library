<?php
/**
 * view/serve_preview.php
 * Secure preview endpoint for approved moderation review files.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();
secure_session_start();

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(403);
    die('Access denied.');
}

$stmt = $conn->prepare(
    "SELECT bf.storage_key, bf.mime, pat.user_id
     FROM pdf_access_tokens pat
     JOIN book_files bf ON pat.book_file_id = bf.id
     WHERE pat.token = ? AND pat.used = 0 AND pat.expires_at > NOW() LIMIT 1"
);
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(403);
    die('Invalid or expired preview token.');
}

$preview = $result->fetch_assoc();
if ((int)$preview['user_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    die('Token mismatch.');
}

$file_path = __DIR__ . '/../uploads/protected_books/' . $preview['storage_key'];
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found.');
}

$mime = !empty($preview['mime']) ? $preview['mime'] : mime_content_type($file_path);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($preview['storage_key']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($file_path);
exit();
