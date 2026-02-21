<?php
// ================================
// NACOS Digital Library | Index Page
// Checks session & redirects accordingly
// ================================

// ---------------- Error Reporting ----------------
// Show all errors for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------------- Include Database ----------------
require_once __DIR__ . '/includes/db.php'; // ensures $conn is available

// ---------------- Start Session Safely ----------------
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------- Session Check ----------------
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];

    // Prepare statement to verify user exists
    $stmt = $conn->prepare("SELECT id, fullname, role FROM users WHERE id = ? LIMIT 1");

    if (!$stmt) {
        // Fatal DB prepare error
        die("Database Prepare Failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        die("Database Execute Failed: " . $stmt->error);
    }

    // Use bind_result instead of get_result for compatibility
    $stmt->bind_result($id, $fullname, $role);

    if ($stmt->fetch()) {
        // User exists → redirect to home page
        $stmt->close();
        header("Location: home.php");
        exit();
    } else {
        // User does not exist → session invalid, destroy it
        $stmt->close();
        session_unset();
        session_destroy();
        header("Location: auth/login.php");
        exit();
    }
} else {
    // No session detected → redirect to login page
    header("Location: auth/login.php");
    exit();
}
