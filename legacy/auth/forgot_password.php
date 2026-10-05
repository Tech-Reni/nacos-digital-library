<?php
require_once __DIR__ . '/../includes/db.php';

// secure_session_start() is called in db.php via helpers/session.php
secure_session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    $matric_number = strtoupper(trim($_POST['matric_number']));

    // Verify user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE matric_number = ? LIMIT 1");
    $stmt->bind_param("s", $matric_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(64));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save to password_resets
        $reset_stmt = $conn->prepare("INSERT INTO password_resets (token, user_id, expires_at) VALUES (?, ?, ?)");
        $reset_stmt->bind_param("sis", $token, $user_id, $expires);
        
        if ($reset_stmt->execute()) {
            // Mock link for testing
            $reset_link = $GLOBALS['BASE_URL'] . "auth/reset_password.php?token=" . $token;
            $success = "If an account exists for this matric number, you will receive a reset link shortly. (Dev Link: <a href='$reset_link'>Reset Link</a>)";
            
            log_audit($conn, "password_reset_requested", "users", $user_id);
        } else {
            $errors[] = "An error occurred. Please try again later.";
        }
        $reset_stmt->close();
    } else {
        // Obfuscate whether user exists
        usleep(200000);
        $success = "If an account exists for this matric number, you will receive a reset link shortly.";
        log_audit($conn, "password_reset_attempt_nonexistent", null, null, ["matric" => $matric_number]);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Request a password reset for NACOS App accounts.">
    <meta name="author" content="NACOS App">
    <meta name="robots" content="noindex">
    <title>NACOS App | Forgot Password</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Forgot Password</h2>
            <p class="subtitle" style="text-align: center; color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">Enter your matric number to receive a reset link.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>" . safe_output($e) . "</p>"; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $BASE_URL ?>auth/forgot_password.php" id="forgotForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <input type="text" name="matric_number" placeholder="Matric Number" value="<?= isset($_POST['matric_number']) ? safe_output($_POST['matric_number']) : '' ?>" required>

                <button type="submit">Request Reset</button>

            </form>

            <p class="switch-link">
                Remember your password? <a href="<?= $BASE_URL ?>auth/login.php">Login</a>
            </p>

        </div>
    </div>

    <script src="<?= $BASE_URL ?>assets/js/auth.js"></script>
</body>

</html>
