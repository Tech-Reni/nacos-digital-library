<?php
require_once __DIR__ . '/../includes/db.php';

// Ensure session helpers and BASE_URL are loaded
secure_session_start();

session_unset();
session_destroy();

header("Location: " . $BASE_URL . "auth/login.php");
exit();