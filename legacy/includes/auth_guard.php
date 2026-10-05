<?php

/**
 * Role-Based Access Control
 * Phase 7 Requirement
 */

require_once __DIR__ . '/session.php';
secure_session_start();

function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $GLOBALS['BASE_URL'] . "auth/login.php");
        exit();
    }

    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    if (!empty($_SESSION['must_change_password']) && $current_page !== 'change_password.php') {
        header("Location: " . $GLOBALS['BASE_URL'] . "auth/change_password.php");
        exit();
    }
}

function check_role($allowed_roles = []) {
    check_auth();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        die("Unauthorized access. You do not have permission to view this page.");
    }
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_governor() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'governor' || $_SESSION['role'] === 'admin');
}

function is_course_rep() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'course_rep' || $_SESSION['role'] === 'governor' || $_SESSION['role'] === 'admin');
}
