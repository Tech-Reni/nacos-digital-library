<?php

/**
 * Database Connection & Environment Configuration
 * Phase 12 Requirement
 */

// 1. SIMPLE .ENV PARSER
function load_env($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

load_env(__DIR__ . '/../.env');

// Never accept credentials over plaintext HTTP in production.
$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$isHttps = $isHttps || $forwardedProto === 'https';
if ((getenv('APP_ENV') ?: 'local') === 'production' && !$isHttps) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit();
}

// 2. CONFIGURATION
// Credentials are read from .env only. The hardcoded production fallbacks that
// used to live here were removed; that password must be rotated on the host.
$servername = getenv('DB_SERVER') ?: '127.0.0.1';
$username   = getenv('DB_USERNAME') ?: '';
$password   = getenv('DB_PASSWORD') ?: '';
$dbname     = getenv('DB_NAME') ?: '';
$BASE_URL   = getenv('BASE_URL') ?: '/';

// Global variables for convenience (Phase 15 - Code Quality)
$GLOBALS['BASE_URL'] = $BASE_URL;

// 3. DATABASE CONNECTION
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // In production, log this and show a generic error
    die("Database Connection Failed: " . $e->getMessage());
}

// 4. INCLUDE HELPERS & SESSION AUTOMATICALLY (Foundation)
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';

// 5. CENTRAL META / SEO HELPERS (provides render_meta() for every page)
require_once __DIR__ . '/meta.php';

secure_session_start();

