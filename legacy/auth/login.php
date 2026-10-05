<?php
require_once __DIR__ . '/../includes/db.php';

// secure_session_start() is called in db.php via helpers/session.php
secure_session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: " . $BASE_URL . "home.php");
    exit();
}

$errors = [];

// Older installations may not yet have the bulk-registration password flag.
try {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    // The column already exists.
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Phase 5: CSRF Verification
    csrf_verify();

    $matric_number = strtoupper(trim($_POST['matric_number']));
    $password = $_POST['password'];
    $device_fp = isset($_POST['device_fp']) ? trim($_POST['device_fp']) : '';

    $sql = "SELECT id, uuid, fullname, department, level, programme, password_hash, role, mfa_enabled, failed_logins, lock_until, device_fp, must_change_password
            FROM users WHERE matric_number = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $matric_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Check for active lockout
        if (!empty($user['lock_until']) && strtotime($user['lock_until']) > time()) {
            $remaining = strtotime($user['lock_until']) - time();
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $errors[] = "Account is temporarily locked. Try again in {$hours}h {$minutes}m.";

            // Phase 13: Log lockout event
            log_audit($conn, "login_locked", "users", $user['id'], ["ip" => $_SERVER['REMOTE_ADDR']]);
        } else {
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Successful password verification
                
                // Phase: MFA Check
                if (!empty($user['mfa_enabled']) && $user['mfa_enabled']) {
                    // MFA is enabled, set temporary session and redirect
                    $_SESSION['mfa_user_id'] = $user['id'];
                    $_SESSION['mfa_pending'] = true;
                    
                    log_audit($conn, "login_password_verified_mfa_pending", "users", $user['id']);
                    header("Location: " . $BASE_URL . "auth/mfa_verify.php");
                    exit();
                }

                // Successful login (No MFA): reset counters, clear lock, update last_login and device_fp if empty
                $update = $conn->prepare(
                  "UPDATE users SET failed_logins = 0, lock_until = NULL, last_login = NOW(), device_fp = IF(device_fp = '', ?, device_fp) WHERE id = ?"
                );
                $update->bind_param("si", $device_fp, $user['id']);
                $update->execute();
                $update->close();

                // If device_fp changed (both non-empty and different) — record audit
                if ($device_fp !== '' && $user['device_fp'] !== '' && strcasecmp($device_fp, $user['device_fp']) !== 0) {
                    log_audit($conn, "device_change_detected", "users", $user['id'], ["old_fp" => $user['device_fp'], "new_fp" => $device_fp]);
                }

                // Phase 6: Session Hardening
                secure_session_regenerate();

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['uuid'] = $user['uuid'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['level'] = $user['level'];
                $_SESSION['programme'] = $user['programme'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['must_change_password'] = (int)$user['must_change_password'];

                // Phase 13: Log successful login
                log_audit($conn, "login_success", "users", $user['id']);

                header("Location: " . $BASE_URL . ($user['must_change_password'] ? "auth/change_password.php" : "home.php"));
                exit();
            } else {
                // Failed password: increment failed_logins; lock if threshold reached
                $failed = (int)$user['failed_logins'] + 1;
                if ($failed >= 5) {
                    $upd = $conn->prepare("UPDATE users SET failed_logins = 0, lock_until = DATE_ADD(NOW(), INTERVAL 6 HOUR) WHERE id = ?");
                    $upd->bind_param("i", $user['id']);
                    $upd->execute();
                    $upd->close();

                    log_audit($conn, "account_locked_failed_attempts", "users", $user['id']);
                } else {
                    $upd = $conn->prepare("UPDATE users SET failed_logins = ? WHERE id = ?");
                    $upd->bind_param("ii", $failed, $user['id']);
                    $upd->execute();
                    $upd->close();
                }

                $errors[] = "Invalid credentials.";
                log_audit($conn, "login_failed_password", "users", $user['id']);
            }
        }
    } else {
        // Generic response — do not reveal account existence; small delay to reduce timing attacks
        usleep(200000);
        $errors[] = "Invalid credentials.";
        log_audit($conn, "login_failed_nonexistent_user", null, null, ["matric" => $matric_number]);
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to NACOS App — access course materials, books and student services.">
    <meta name="author" content="NACOS App">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0a74da">
    <meta property="og:title" content="NACOS App — Login">
    <meta property="og:description" content="Sign in to access NACOS App student services and protected academic resources.">
    <meta property="og:image" content="<?= $BASE_URL ?>assets/images/YCT_LOGO.png">
    <meta name="twitter:card" content="summary_large_image">
    <title>NACOS App | Login</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Welcome Back</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>" . safe_output($e) . "</p>"; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $BASE_URL ?>auth/login.php" id="loginForm" autocomplete="on">
                <!-- Phase 5: CSRF Token -->
                <input type="hidden" id="login_csrf_token" name="csrf_token" value="<?= csrf_token() ?>">

                <input type="text" id="login_matric_number" name="matric_number" placeholder="Matric Number" value="<?= isset($_POST['matric_number']) ? safe_output($_POST['matric_number']) : '' ?>" required autocomplete="username">

                <div class="password-wrapper">
                    <input type="password" name="password" id="login_password" placeholder="Password" required autocomplete="current-password">
                    <i class="ri-eye-line toggle-eye" data-target="login_password"></i>
                </div>

                <p class="forgot-link"><a href="<?= $BASE_URL ?>auth/forgot_password.php">Forgot Password ?</a></p>

                <button type="submit">Login</button>

            </form>

            <p class="switch-link">
                Don't have an account? <a href="<?= $BASE_URL ?>auth/signup.php">Sign Up</a>
            </p>

        </div>
    </div>

    <script src="<?= $BASE_URL ?>assets/js/auth.js"></script>
</body>

</html>