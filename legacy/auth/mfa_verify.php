<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mfa_helpers.php';

secure_session_start();

try {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    // The column already exists.
}

// Ensure there is a pending MFA session
if (!isset($_SESSION['mfa_user_id']) || !isset($_SESSION['mfa_pending'])) {
    header("Location: " . $BASE_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['mfa_user_id'];
$errors = [];

// Fetch user data
$stmt = $conn->prepare("SELECT id, uuid, fullname, department, level, programme, role, mfa_secret, mfa_backup_codes, device_fp, must_change_password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: " . $BASE_URL . "auth/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    $otp = trim($_POST['otp']);
    $success = false;
    $method = 'totp';

    // Check if it's a backup code (format: 1234-5678)
    if (preg_match('/^\d{4}-\d{4}$/', $otp)) {
        $method = 'backup_code';
        $backup_codes = json_decode($user['mfa_backup_codes'], true) ?: [];
        
        if (($key = array_search($otp, $backup_codes)) !== false) {
            unset($backup_codes[$key]);
            
            // Update backup codes
            $new_codes = json_encode(array_values($backup_codes));
            $upd = $conn->prepare("UPDATE users SET mfa_backup_codes = ? WHERE id = ?");
            $upd->bind_param("si", $new_codes, $user_id);
            $upd->execute();
            $upd->close();
            
            $success = true;
        }
    } else {
        // Standard TOTP verification
        if (TOTP_Helper::verifyOTP($user['mfa_secret'], $otp)) {
            $success = true;
        }
    }

    if ($success) {
        // Successful MFA verification: Finalize login
        $device_fp = isset($_POST['device_fp']) ? trim($_POST['device_fp']) : '';
        
        $update = $conn->prepare(
          "UPDATE users SET failed_logins = 0, lock_until = NULL, last_login = NOW(), device_fp = IF(device_fp = '', ?, device_fp) WHERE id = ?"
        );
        $update->bind_param("si", $device_fp, $user['id']);
        $update->execute();
        $update->close();

        // Phase 6: Session Hardening
        secure_session_regenerate();
        
        unset($_SESSION['mfa_user_id']);
        unset($_SESSION['mfa_pending']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['uuid'] = $user['uuid'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['level'] = $user['level'];
        $_SESSION['programme'] = $user['programme'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['must_change_password'] = (int)$user['must_change_password'];

        log_audit($conn, "mfa_verified_success", "users", $user['id'], ["method" => $method]);
        log_audit($conn, "login_success", "users", $user['id'], ["mfa" => true]);

        header("Location: " . $BASE_URL . ($user['must_change_password'] ? "auth/change_password.php" : "home.php"));
        exit();
    } else {
        $errors[] = "Invalid verification code.";
        log_audit($conn, "mfa_verified_failed", "users", $user['id'], ["method" => $method]);
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Complete two-factor authentication to securely access your NACOS App account.">
    <title>NACOS App | MFA Verification</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Two-Factor Auth</h2>
            <p class="subtitle" style="text-align: center; color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">
                Enter the code from your authenticator app or a backup recovery code.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>" . safe_output($e) . "</p>"; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <input type="text" name="otp" placeholder="Enter 6-digit code or backup code" required autofocus autocomplete="one-time-code">

                <button type="submit">Verify & Login</button>

            </form>

            <p class="switch-link">
                Having trouble? <a href="<?= $BASE_URL ?>auth/logout.php">Cancel & Login Again</a>
            </p>

        </div>
    </div>

    <script src="<?= $BASE_URL ?>assets/js/auth.js"></script>
</body>

</html>
