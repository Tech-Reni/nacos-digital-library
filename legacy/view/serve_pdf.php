<?php
/**
 * view/serve_pdf.php
 * Phase 9: Secure PDF Streaming (Proxy)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
secure_session_start();

$token = $_GET['token'] ?? '';

if (!$token) {
    http_response_code(403);
    die("Access denied.");
}

// Verify token (Phase 9: Permission enforcement)
$stmt = $conn->prepare("SELECT bf.storage_key, pat.user_id 
                        FROM pdf_access_tokens pat 
                        JOIN book_files bf ON pat.book_file_id = bf.id 
                        WHERE pat.token = ? AND pat.used = 0 AND pat.expires_at > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(403);
    die("Invalid or expired access token.");
}

$data = $result->fetch_assoc();

// Ensure the token belongs to the current session user
if ($data['user_id'] !== $_SESSION['user_id']) {
    http_response_code(403);
    die("Token mismatch.");
}

$file_path = __DIR__ . '/../uploads/protected_books/' . $data['storage_key'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die("File not found.");
}

// Mark token as used or keep it for the session duration? 
// For iFrames, we might want to keep it valid for one hit or a short window.
// $conn->query("UPDATE pdf_access_tokens SET used = 1 WHERE token = '$token'");

// Stream the file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="document.pdf"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($file_path);
exit();
