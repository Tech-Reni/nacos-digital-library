<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric_number = strtoupper(trim($_POST['matric_number'] ?? ''));

    // In a real app we'd generate a token and send an email. Here we show a generic message.
    if ($matric_number !== '') {
        $message = 'If an account with that Matric Number exists, a password reset link will be sent.';
    } else {
        $message = 'Please provide your Matric Number.';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Request a password reset for NACOS Digital Library accounts.">
    <meta name="author" content="NACOS Digital Library">
    <meta name="robots" content="noindex">
    <title>NACOS Digital Library | Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="icon" href="../assets/images/YCT_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="../assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Forgot Password</h2>

            <?php if ($message): ?>
                <div class="alert info">
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" id="forgotForm">

                <input type="text" name="matric_number" placeholder="Matric Number" required>

                <button type="submit">Request Reset</button>

            </form>

            <p class="switch-link">
                Remembered? <a href="login.php">Login</a>
            </p>

        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>

</html>
