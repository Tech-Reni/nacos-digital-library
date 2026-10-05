<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

check_auth();

try {
    $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    // The column already exists.
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!is_password_strong($password)) {
        $errors[] = 'Password must be 8-12 characters long and include uppercase, lowercase, number, and special character.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
        $stmt->bind_param('si', $hash, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $_SESSION['must_change_password'] = 0;
            log_audit($conn, 'password_changed', 'users', (int)$_SESSION['user_id']);
            header('Location: ' . $BASE_URL . 'home.php');
            exit;
        }
        $errors[] = 'Unable to update your password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Set a new secure password for your NACOS App account.">
    <title>Change Password | NACOS App</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">
            <h2>Change Your Password</h2>
            <p class="subtitle" style="text-align:center; color:var(--text-secondary); font-size:14px; margin-bottom:20px;">Your account was created with a temporary password. Choose a new password to continue.</p>
            <?php if (!empty($errors)): ?>
                <div class="alert error"><?php foreach ($errors as $error) echo '<p>' . safe_output($error) . '</p>'; ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= $BASE_URL ?>auth/change_password.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="password-wrapper">
                    <input type="password" name="password" placeholder="New Password" required autocomplete="new-password">
                </div>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required autocomplete="new-password">
                </div>
                <button type="submit">Save New Password</button>
            </form>
        </div>
    </div>
</body>
</html>
