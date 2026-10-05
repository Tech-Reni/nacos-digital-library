<?php
/**
 * ai_handler.php
 * Phase 18: NACOS App Concierge Logic
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

// Ensure user is logged in
check_auth();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Message is empty.']);
    exit();
}

$response = "";
$message_lower = strtolower($message);

// 1. Search Logic (Books)
if (strpos($message_lower, 'book') !== false || strpos($message_lower, 'find') !== false || strpos($message_lower, 'search') !== false) {
    $search = preg_replace('/(find|search|books|about|for)/i', '', $message);
    $search = trim($search);
    
    if (empty($search)) {
        $response = "Which book are you looking for? You can say something like <strong>'Find Database Systems'</strong>.";
    } else {
        $stmt = $conn->prepare("SELECT title, author, uuid FROM books WHERE title LIKE ? AND status = 'approved' LIMIT 3");
        $term = "%$search%";
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $response = "I found these books matching <em>" . safe_output($search) . "</em>: <br><ul style='margin-top:10px; padding-left:20px;'>";
            while($b = $res->fetch_assoc()) {
                $response .= "<li style='margin-bottom:8px;'><a href='" . $BASE_URL . "view/reader.php?uuid=" . $b['uuid'] . "' style='color:var(--primary-green); text-decoration:none;'><strong>" . safe_output($b['title']) . "</strong></a> by " . safe_output($b['author']) . "</li>";
            }
            $response .= "</ul>";
        } else {
            $response = "I couldn't find any approved books matching '" . safe_output($search) . "'. Try a different keyword!";
        }
    }
} 
// 2. Approval Status Logic
elseif (strpos($message_lower, 'approval') !== false || strpos($message_lower, 'status') !== false || strpos($message_lower, 'review') !== false) {
    $stmt = $conn->prepare("SELECT title, status FROM books WHERE uploader_id = ? ORDER BY created_at DESC LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $response = "Here is the status of your recent uploads: <br><ul style='margin-top:10px; padding-left:20px;'>";
        while($b = $res->fetch_assoc()) {
            $status_color = ($b['status'] == 'approved') ? 'var(--primary-green)' : (($b['status'] == 'rejected') ? 'var(--danger)' : 'var(--primary-yellow)');
            $response .= "<li style='margin-bottom:8px;'>" . safe_output($b['title']) . ": <strong style='color:$status_color;'>" . ucfirst($b['status']) . "</strong></li>";
        }
        $response .= "</ul>";
    } else {
        $response = "You haven't uploaded any books yet. Want to <a href='" . $BASE_URL . "upload/upload.php' style='color:var(--primary-green);'>upload one now</a>?";
    }
} 
// 3. Bookmarks Logic
elseif (strpos($message_lower, 'bookmark') !== false || strpos($message_lower, 'saved') !== false) {
    $stmt = $conn->prepare("SELECT b.title, b.uuid FROM bookmarks bm JOIN books b ON bm.book_id = b.id WHERE bm.user_id = ? LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $response = "Here are your saved bookmarks: <br><ul style='margin-top:10px; padding-left:20px;'>";
        while($b = $res->fetch_assoc()) {
            $response .= "<li style='margin-bottom:8px;'><a href='" . $BASE_URL . "view/reader.php?uuid=" . $b['uuid'] . "' style='color:var(--primary-green); text-decoration:none;'>" . safe_output($b['title']) . "</a></li>";
        }
        $response .= "</ul>";
    } else {
        $response = "Your bookmark list is currently empty. You can save books by clicking the bookmark icon in the library.";
    }
} 
// 4. Notifications Logic
elseif (strpos($message_lower, 'notification') !== false || strpos($message_lower, 'alert') !== false || strpos($message_lower, 'news') !== false) {
    $stmt = $conn->prepare("SELECT title, message FROM notifications WHERE user_id = ? AND is_read = 0 LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $response = "You have unread alerts: <br><ul style='margin-top:10px; padding-left:20px;'>";
        while($n = $res->fetch_assoc()) {
            $response .= "<li style='margin-bottom:8px;'><strong>" . safe_output($n['title']) . "</strong>: " . safe_output($n['message']) . "</li>";
        }
        $response .= "</ul>";
    } else {
        $response = "All caught up! You have no new notifications.";
    }
} 
// 5. Activity Logic
elseif (strpos($message_lower, 'activity') !== false || strpos($message_lower, 'what did i do') !== false || strpos($message_lower, 'history') !== false) {
    $stmt = $conn->prepare("SELECT action, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $response = "Your recent activities: <br><ul style='margin-top:10px; padding-left:20px;'>";
        while($a = $res->fetch_assoc()) {
            $response .= "<li style='margin-bottom:8px;'>" . str_replace('_', ' ', ucfirst($a['action'])) . " <span style='font-size:11px; color:#666;'>(" . date('M d', strtotime($a['created_at'])) . ")</span></li>";
        }
        $response .= "</ul>";
    } else {
        $response = "I don't have any recent activity records for your account.";
    }
} 
// 6. Help / Default
else {
    $response = "I'm the NACOS App Concierge. I can help you with:<br>
                 • Finding specific <strong>books</strong><br>
                 • Checking your <strong>upload status</strong><br>
                 • Accessing your <strong>bookmarks</strong><br>
                 • Viewing <strong>notifications</strong><br>
                 • Tracking your <strong>recent activity</strong><br>
                 What would you like to know?";
}

echo json_encode(['status' => 'success', 'response' => $response]);
exit();
