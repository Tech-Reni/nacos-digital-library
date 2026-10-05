<?php
require_once __DIR__ . '/../includes/db.php';
secure_session_start();

$errors = [];
$success = "";
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    header("Location: " . $BASE_URL . "auth/login.php");
    exit();
}

// Verify token
$stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Invalid or expired reset token.");
}

$reset_data = $result->fetch_assoc();
$user_id = $reset_data['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    $password = $_POST['password'];
    $confirm_pw = $_POST['confirm_password'];

    if ($password !== $confirm_pw) {
        $errors[] = "Passwords do not match.";
    }

    if (!is_password_strong($password)) {
        $errors[] = "Password must be 8-12 characters long and include uppercase, lowercase, number, and special character.";
    }

    if (empty($errors)) {
        // Update password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $upd_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd_stmt->bind_param("si", $password_hash, $user_id);

        if ($upd_stmt->execute()) {
            // Mark token as used
            $mark_stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $mark_stmt->bind_param("s", $token);
            $mark_stmt->execute();
            
            $success = "Password updated successfully. <a href='" . $BASE_URL . "auth/login.php'>Login Now</a>";
            log_audit($conn, "password_reset_success", "users", $user_id);
        } else {
            $errors[] = "Database error. Please try again.";
        }
        $upd_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your NACOS App account password securely.">
    <title>NACOS App | Reset Password</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Reset Password</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>" . safe_output($e) . "</p>"; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <?= $success ?>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= $BASE_URL ?>auth/reset_password.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="token" value="<?= safe_output($token) ?>">

                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="New Password" required autocomplete="new-password">
                        <i class="ri-eye-line toggle-eye" data-target="password"></i>
                    </div>

                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required autocomplete="new-password">
                        <i class="ri-eye-line toggle-eye" data-target="confirm_password"></i>
                    </div>

                    <button type="submit">Update Password</button>

                </form>
            <?php endif; ?>

        </div>
    </div>

    <script src="<?= $BASE_URL ?>assets/js/auth.js"></script>
</body>

</html>
